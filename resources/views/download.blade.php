<!doctype html>
<html lang="ar">
<head>
	 <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>download</title>


 <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

</head>
<body>  
 




<div class="container" style="text-align: center; margin-top: 250px">
  <div>تم تحويل الملف بنجاح الرجاء الضغط للتحميل وفك الاضغط واضافتة لذاكرة القارئ</div>
  <a href="{{ asset('/storage/zip/'.$implode.'.zip') }}" class="btn btn-info" > تحميل ملف</a>  
</body>
</html>
