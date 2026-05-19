#!/usr/bin/env python3
"""One-shot namespace rewrite for the domain-folder cleanup."""
import glob

renames = [
    ('app/Domains/Workflows/Models/*.php', 'App\\Domains\\Governance\\Models',   'App\\Domains\\Workflows\\Models'),
    ('app/Domains/Central/Models/*.php',   'App\\Domains\\Integration\\Models', 'App\\Domains\\Central\\Models'),
    ('app/Domains/Central/Models/*.php',   'App\\Domains\\SystemOps\\Models',   'App\\Domains\\Central\\Models'),
    ('app/Domains/Analytics/Models/*.php', 'App\\Domains\\SystemOps\\Models',   'App\\Domains\\Analytics\\Models'),
    ('app/Domains/Grading/Models/AssessmentResult.php',
        'App\\Domains\\Governance\\Models\\ApprovalWorkflow',
        'App\\Domains\\Workflows\\Models\\ApprovalWorkflow'),
]

touched = 0
for pattern, old, new in renames:
    for path in glob.glob(pattern):
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        if old in content:
            with open(path, 'w', encoding='utf-8') as f:
                f.write(content.replace(old, new))
            touched += 1
            print(f"  rewrote: {path}")

print(f"--- {touched} files rewritten ---")
