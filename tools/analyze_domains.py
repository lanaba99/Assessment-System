#!/usr/bin/env python3
r"""
analyze_domains.py
-------------------
Static analysis tool for Laravel DDD projects (app/Domains/* structure).

What it does:
  1. Scans every PHP file inside app/Domains/<Domain>/...
  2. Extracts cross-domain `use App\Domains\X\...` imports -> direct code dependencies
  3. Finds Event classes (Domains/*/Events/*.php) and which domain owns them
  4. Finds Event::listen(...) registrations inside *ServiceProvider.php files
     -> builds the event -> listener -> owning-domain map
  5. Detects circular dependencies (A uses B AND B uses A)
  6. Computes fan-in (how many domains depend on each domain) - highest fan-in
     domains are your most "core"/riskiest-to-change domains
  7. Outputs:
       - a human-readable report to stdout
       - a Mermaid flowchart (.mmd) for direct dependencies
       - a Mermaid flowchart (.mmd) for the event-driven map
       - a JSON dump with all raw data, for further processing

Usage:
    python3 analyze_domains.py /path/to/your/laravel/project
    (defaults to current directory if no path given)

Requirements: Python 3.8+, no external packages.
"""

import re
import sys
import json
from pathlib import Path
from collections import defaultdict


def find_domains_root(project_root: Path) -> Path:
    candidates = [
        project_root / "app" / "Domains",
        project_root / "app" / "Domain",
    ]
    for c in candidates:
        if c.is_dir():
            return c
    print(f"ERROR: could not find app/Domains or app/Domain under {project_root}")
    sys.exit(1)


def extract_direct_dependencies(domains_root: Path, domain_names):
    """Scan every php file for `use App\\Domains\\X\\...;` imports pointing to another domain."""
    use_pattern = re.compile(r'^use\s+([A-Za-z0-9_\\]+);', re.MULTILINE)
    direct_deps = defaultdict(lambda: defaultdict(set))  # src -> tgt -> set of (file, used_class)

    domains_ns_prefix = "App\\Domains\\"  # adjust if your namespace differs

    for domain in domain_names:
        domain_path = domains_root / domain
        for php_file in domain_path.rglob("*.php"):
            try:
                content = php_file.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                continue
            for used_class in use_pattern.findall(content):
                if used_class.startswith(domains_ns_prefix):
                    rest = used_class[len(domains_ns_prefix):]
                    target_domain = rest.split("\\", 1)[0]
                    if target_domain != domain:
                        rel_file = str(php_file.relative_to(domains_root))
                        direct_deps[domain][target_domain].add((rel_file, used_class))
    return direct_deps


def extract_events(domains_root: Path, domain_names):
    """Find Event classes per domain: app/Domains/<Domain>/Events/*.php"""
    events = {}  # event_class_short_name -> {"domain":..., "fqcn":..., "file":...}
    for domain in domain_names:
        events_dir = domains_root / domain / "Events"
        if not events_dir.is_dir():
            continue
        for php_file in events_dir.glob("*.php"):
            class_name = php_file.stem
            fqcn = f"App\\Domains\\{domain}\\Events\\{class_name}"
            events[class_name] = {
                "domain": domain,
                "fqcn": fqcn,
                "file": str(php_file.relative_to(domains_root)),
            }
    return events


def extract_listener_registrations(domains_root: Path, domain_names, events):
    """
    Find `Event::listen(SomeEvent::class, [SomeListener::class, 'handle']);`
    inside any *ServiceProvider.php file, and resolve which domain the
    listener and event belong to (using their `use` imports in that file).
    """
    listen_pattern = re.compile(
        r'Event::listen\(\s*([A-Za-z0-9_]+)::class\s*,\s*\[\s*([A-Za-z0-9_]+)::class'
    )
    use_pattern = re.compile(r'^use\s+([A-Za-z0-9_\\]+);', re.MULTILINE)

    registrations = []  # list of dicts: event, event_domain, listener, listener_domain, provider_file

    for domain in domain_names:
        for php_file in (domains_root / domain).rglob("*ServiceProvider.php"):
            try:
                content = php_file.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                continue

            uses = use_pattern.findall(content)
            short_to_fqcn = {}
            for u in uses:
                short = u.rsplit("\\", 1)[-1]
                short_to_fqcn[short] = u

            for event_short, listener_short in listen_pattern.findall(content):
                event_fqcn = short_to_fqcn.get(event_short, event_short)
                listener_fqcn = short_to_fqcn.get(listener_short, listener_short)

                event_domain_match = re.match(r"App\\Domains\\([A-Za-z0-9_]+)\\Events", event_fqcn)
                listener_domain_match = re.match(r"App\\Domains\\([A-Za-z0-9_]+)\\Listeners", listener_fqcn)

                registrations.append({
                    "event": event_short,
                    "event_fqcn": event_fqcn,
                    "event_domain": event_domain_match.group(1) if event_domain_match else "UNKNOWN",
                    "listener": listener_short,
                    "listener_fqcn": listener_fqcn,
                    "listener_domain": listener_domain_match.group(1) if listener_domain_match else domain,
                    "registered_in": str(php_file.relative_to(domains_root)),
                })
    return registrations


def find_circular(direct_deps):
    pairs = set()
    for src, tgts in direct_deps.items():
        for tgt in tgts:
            pairs.add((src, tgt))

    circular = []
    seen = set()
    for (a, b) in pairs:
        if (b, a) in pairs and (b, a) not in seen:
            seen.add((a, b))
            circular.append((
                a, b,
                len(direct_deps[a][b]), len(direct_deps[b][a])
            ))
    return circular


def compute_fanin(direct_deps, exclude=("Shared",)):
    fanin = defaultdict(int)
    for src, tgts in direct_deps.items():
        for tgt in tgts:
            if tgt not in exclude:
                fanin[tgt] += 1
    return dict(sorted(fanin.items(), key=lambda x: -x[1]))


def mermaid_direct_deps(direct_deps, circular_pairs, exclude=("Shared",)):
    """Generate a Mermaid flowchart for direct code dependencies.
    Circular pairs get a distinct color so they jump out visually."""
    circ_set = set()
    for a, b, _, _ in circular_pairs:
        circ_set.add((a, b))
        circ_set.add((b, a))

    lines = ["flowchart LR"]
    edge_idx = 0
    edge_styles = []
    for src in sorted(direct_deps):
        for tgt in sorted(direct_deps[src]):
            if tgt in exclude:
                continue
            n = len(direct_deps[src][tgt])
            lines.append(f'    {src} -->|"{n}"| {tgt}')
            if (src, tgt) in circ_set:
                edge_styles.append(f"    linkStyle {edge_idx} stroke:#D85A30,stroke-width:2px")
            edge_idx += 1
    lines.extend(edge_styles)
    return "\n".join(lines)


def mermaid_events(events, registrations):
    lines = ["flowchart TD"]
    lines.append("    classDef domainBox fill:#EEEDFE,stroke:#534AB7")
    lines.append("    classDef eventBox fill:#FAECE7,stroke:#D85A30")

    drawn_domains = set()
    drawn_events = set()

    for reg in registrations:
        ev_domain = reg["event_domain"]
        ev_name = reg["event"]
        listener_domain = reg["listener_domain"]
        listener_name = reg["listener"]

        ev_node = f'EV_{ev_name}'
        src_node = f'D_{ev_domain}'
        tgt_node = f'D_{listener_domain}'

        if src_node not in drawn_domains:
            lines.append(f'    {src_node}["{ev_domain}"]:::domainBox')
            drawn_domains.add(src_node)
        if tgt_node not in drawn_domains:
            lines.append(f'    {tgt_node}["{listener_domain}"]:::domainBox')
            drawn_domains.add(tgt_node)
        if ev_node not in drawn_events:
            lines.append(f'    {ev_node}("{ev_name}"):::eventBox')
            drawn_events.add(ev_node)

        lines.append(f'    {src_node} -- fires --> {ev_node}')
        lines.append(f'    {ev_node} -. "{listener_name}" .-> {tgt_node}')

    return "\n".join(lines)


def print_report(domain_names, direct_deps, circular, fanin, events, registrations):
    print("=" * 70)
    print("DOMAIN DEPENDENCY ANALYSIS REPORT")
    print("=" * 70)

    print(f"\nDomains found ({len(domain_names)}): {', '.join(sorted(domain_names))}")

    print("\n--- DIRECT (code-level, `use` statement) DEPENDENCIES ---")
    for src in sorted(direct_deps):
        for tgt in sorted(direct_deps[src]):
            n = len(direct_deps[src][tgt])
            print(f"  {src:15s} -> {tgt:15s}  ({n} usages)")

    print("\n--- CIRCULAR DEPENDENCIES (A uses B AND B uses A) ---")
    if not circular:
        print("  None found. Clean!")
    else:
        for a, b, n1, n2 in circular:
            print(f"  {a} <-> {b}    ({a}->{b}: {n1} usages | {b}->{a}: {n2} usages)")
        print(f"\n  Total circular pairs: {len(circular)}")
        print("  NOTE: a circular dependency at the code (use-statement) level means")
        print("  the two domains cannot be deployed, tested, or reasoned about in")
        print("  isolation -- changing one risks breaking the other in both directions.")
        print("  This is different from event-driven communication, which is one-way")
        print("  and decoupled by design.")

    print("\n--- FAN-IN (number of OTHER domains that depend on this one) ---")
    print("  Higher fan-in = more 'core' = riskier to change without wide impact.")
    for d, c in fanin.items():
        print(f"  {d:15s} depended on by {c} domain(s)")

    print("\n--- EVENTS (domain -> event class) ---")
    for ev_name, info in sorted(events.items()):
        print(f"  {info['domain']:15s} fires  {ev_name}")

    print("\n--- EVENT REGISTRATIONS (event -> listener, cross-domain only) ---")
    for reg in registrations:
        marker = "  [cross-domain]" if reg["event_domain"] != reg["listener_domain"] else "  [same-domain]"
        print(f"  {reg['event']:25s} ({reg['event_domain']:12s}) -> {reg['listener']:35s} ({reg['listener_domain']}){marker}")


def main():
    project_root = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path.cwd()
    domains_root = find_domains_root(project_root)
    domain_names = sorted([d.name for d in domains_root.iterdir() if d.is_dir()])

    direct_deps = extract_direct_dependencies(domains_root, domain_names)
    circular = find_circular(direct_deps)
    fanin = compute_fanin(direct_deps)
    events = extract_events(domains_root, domain_names)
    registrations = extract_listener_registrations(domains_root, domain_names, events)

    print_report(domain_names, direct_deps, circular, fanin, events, registrations)

    out_dir = project_root / "domain_analysis_output"
    out_dir.mkdir(exist_ok=True)

    # Mermaid: direct deps
    mmd_direct = mermaid_direct_deps(direct_deps, circular)
    (out_dir / "direct_dependencies.mmd").write_text(mmd_direct, encoding="utf-8")

    # Mermaid: events
    mmd_events = mermaid_events(events, registrations)
    (out_dir / "event_map.mmd").write_text(mmd_events, encoding="utf-8")

    # JSON dump
    json_data = {
        "domains": domain_names,
        "direct_dependencies": {
            src: {tgt: sorted(list(v)) for tgt, v in tgts.items()}
            for src, tgts in direct_deps.items()
        },
        "circular_dependencies": [
            {"domain_a": a, "domain_b": b, "a_to_b_usages": n1, "b_to_a_usages": n2}
            for a, b, n1, n2 in circular
        ],
        "fan_in": fanin,
        "events": events,
        "event_registrations": registrations,
    }
    (out_dir / "analysis.json").write_text(
        json.dumps(json_data, indent=2, ensure_ascii=False), encoding="utf-8"
    )

    print(f"\n\nFiles written to: {out_dir}")
    print(f"  - direct_dependencies.mmd  (open in https://mermaid.live or VS Code Mermaid extension)")
    print(f"  - event_map.mmd            (same)")
    print(f"  - analysis.json            (raw data for further processing)")


if __name__ == "__main__":
    main()