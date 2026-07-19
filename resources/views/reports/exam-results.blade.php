<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #999; padding: 6px; font-size: 12px; text-align: left; }
</style></head>
<body>
<h2>Exam Results Report</h2>
<table>
<tr><th>Candidate</th><th>Score</th><th>Grade</th><th>Passing</th><th>Graded At</th></tr>
@foreach($grades as $g)
<tr>
<td>{{ $g->candidate->first_name ?? '' }} {{ $g->candidate->last_name ?? '' }}</td>
<td>{{ $g->final_score }}</td>
<td>{{ $g->grade_letter }}</td>
<td>{{ $g->is_passing_grade ? 'Yes' : 'No' }}</td>
<td>{{ optional($g->graded_at)->format('Y-m-d H:i') }}</td>
</tr>
@endforeach
</table>
</body>
</html>