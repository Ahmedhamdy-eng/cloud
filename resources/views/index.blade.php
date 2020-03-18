<!doctype html>
<html lang="ar">
<head>
	 <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>Upload File</title>


 <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

</head>
<body>  
  @if (\Session::has('success'))
  <div class="alert alert-success">
      <ul>
          <li>{!! \Session::get('success') !!}</li>
      </ul>
  </div>
@endif

@if(isset($implode))
{{$implode}}
    
@endif
 <form method="post" action="{{url('/store')}}" enctype="multipart/form-data">
        @csrf
  <div class="form-group">
<label>رفع ملف</label>
    <input type="file" class="form-control" name="file"  placeholder="رفع ملف">
  </div>

  <button type="submit" class="btn btn-primary"> رفع الملف</button>
</form>



</body>
</html>
