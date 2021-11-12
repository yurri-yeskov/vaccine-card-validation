<html>
    <head>
        <title>Upload PNG, JPG or PDF file to OCR.space</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/css/bootstrap.min.css">
        <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>
        <script src='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/js/bootstrap.min.js'></script>
    </head>
    <body>
    <form id="upload" method='POST' action='./processing.php' enctype="multipart/form-data">
            <div>
                <div class="fb-file form-group "><label for="attachment" class="fb-file-label">Upload PNG, JPG or PDF File<span class="fb-required"> *</span><span class="tooltip-element" tooltip="choose your pdf">?</span></label>
                <input type="file" placeholder="choose your pdf" class="form-control" name="attachment"  title="choose your pdf" required="required" aria-required="true">
                </div>
                <div class="fb-button form-group "><button type="submit" class="btn btn-success" name="submit" >upload</button></div>
            </div>
    </form>
    </body>
</html>