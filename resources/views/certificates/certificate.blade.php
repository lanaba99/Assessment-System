<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; text-align: center; padding: 60px; border: 10px solid #2c3e50; }
    h1 { color: #2c3e50; font-size: 32px; }
    .name { font-size: 26px; margin: 20px 0; font-weight: bold; }
    .details { font-size: 14px; color: #555; margin: 10px 0; }
    .qr { margin-top: 40px; }
</style>
</head>
<body>
    <h1>Certificate of Achievement</h1>
    <p>This certifies that</p>
    <div class="name">{{ $candidateName }}</div>
    <p>has successfully completed</p>
    <div class="name">{{ $examName }}</div>
    <div class="details">Score: {{ $finalScore }} — Grade: {{ $gradeLetter }}</div>
    <div class="details">Certificate No: {{ $certificateNumber }}</div>
    <div class="details">Issued: {{ $issuedAt }}</div>
    <div class="qr">
        <img src="{{ $qrDataUri }}" width="120" height="120">
        <p class="details">Scan to verify</p>
    </div>
</body>
</html>