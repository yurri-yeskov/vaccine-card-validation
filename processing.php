<?php
if(isset($_POST['submit']) && isset($_FILES)) {
  require __DIR__ . '/vendor/autoload.php';
  $target_dir = "uploads/";
  $uploadOk = 1;
  $fileType = strtolower(pathinfo($_FILES["attachment"]["name"],PATHINFO_EXTENSION));
  $target_file = $target_dir . generateRandomString() .'.'.$fileType;
  // Check file size
  if ($_FILES["attachment"]["size"] > 1048576) { // the file size should be smaller than 1 MB(= 1048576 byte)
    header('HTTP/1.0 403 Forbidden');
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
  }
  if($fileType != "pdf" && $fileType != "png" && $fileType != "jpg" && $fileType != "jpeg") {
    header('HTTP/1.0 403 Forbidden');
    echo "Sorry, please upload a PNG, JPG or PDF file";
    $uploadOk = 0;
  }
  if ($uploadOk == 1) {
  
    if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
      uploadToApi($target_file);
    } else {
      header('HTTP/1.0 403 Forbidden');
      echo "Not uploaded because of error #".$_FILES["attachment"]["error"];
    }
  } 
} else {
  header('HTTP/1.0 403 Forbidden');
  echo "Sorry, please upload a PNG, JPG or PDF file";
}

function uploadToApi($target_file) {
  require __DIR__ . '/vendor/autoload.php';
  $fileData = fopen($target_file, 'r');
  $client = new \GuzzleHttp\Client();
  try {
  $r = $client->request('POST', 'https://api.ocr.space/parse/image', [
    'headers' => ['apiKey' => '1abd3084f488957'],
    'multipart' => [
      [
        'name' => 'file',
        'contents' => $fileData
      ],
      [
        'name' => 'isTable',
        'contents' => 'true'
      ],
      [
        'name' => 'OCREngine',
        'contents' => '2'
      ]
    ]
  ]);
  $response =  json_decode($r->getBody(), true);
  // print_r($response);
  // foreach($response['ParsedResults'] as $pareValue) {
  //   $errorMessage = $pareValue['ErrorMessage'];
  // }
  if(!isset($response['ErrorMessage'])) {
    $compareTextArray = [
      "COVID-19 Vaccination Record Card",
      "Please keep this record card, which includes medical information",
      "about the vaccines you have received.",
      "Por favor, guarde esta tarjeta de registro, que incluye información",
      "médica sobre las vacunas que ha recibido.",
      "Last Name",
      "Date of birth",
      "First Name",
      "MI",
      "Patient number (medical record or IIS record number)",
      "Product Name/Manufacturer",
      "Vaccine",
      "Lot Number",
      "1st Dose",
      "2nd Dose",
      "COVID-19",
      "Date",
      "Healthcare Professional",
      "or Clinic Site",
      "Other",
      "mm dd yy"
    ];
    $similarityRateArray = [];
    foreach($response['ParsedResults'] as $pareValue) {
      // echo $pareValue['ParsedText'];
      $pareValueArray = explode("\t", $pareValue['ParsedText']);
      foreach($pareValueArray as $value) {
        foreach($compareTextArray as $compareText) {
          if (preg_replace('/\s+/', '', $value) == "") break;
          $similarityRate = similarity($value, $compareText);
          if ($similarityRate > 0.95) {
            array_push($similarityRateArray, $similarityRate);
            // echo "--------------------------\n";
            // echo $similarityRate."\n";
            break;
          } else {
            $valueLen = strlen(preg_replace('/\s+/', '', $value));
            $diffCount = $valueLen - $valueLen * $similarityRate;
            if ($diffCount <= 1) {
              array_push($similarityRateArray, $similarityRate);
              // echo "--------------------------\n";
              // echo $similarityRate."\n";
              break;
            }
          }
        }
      }
    }
    $average = 2;
    $idx = 0;
    while ($average > 1) {
      $average = array_sum($similarityRateArray)/(count($compareTextArray) + 1);
      $idx++;
    }
    $result_percent = $average * 100;
?>
<html>
    <head>
    <title>Result</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/css/bootstrap.min.css">
      <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>
      <script src='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/js/bootstrap.min.js'></script>
    </head>
    <body>
      <div class="form-group container mt-4">
        <div class="d-flex" style="justify-content: space-between; align-items: center">
          <label>Result</label>
          <label>Authenticity Reliability: <?php echo round($result_percent, 2) ?> %</label>
        </div>
        <textarea class="form-control" id="resultTextarea" rows="30">
        <?php
          foreach($pareValueArray as $value) {
            echo $value;
            echo "\n";
          }
        ?></textarea>
      </div>
    </body>
</html>
<?php
  } else {
    header('HTTP/1.0 400 Forbidden');
    echo $response['ErrorMessage'][0];
  }
  } catch(Exception $err) {
    header('HTTP/1.0 403 Forbidden');
    echo $err->getMessage();
  }
}

function generateRandomString($length = 10) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

function similarity($s1, $s2) {
  $ss1 = preg_replace('/\s+/', '', $s1);
  $ss2 = preg_replace('/\s+/', '', $s2);
  $longer = $ss1;
  $shorter = $ss2;
  if (strlen($ss1) < strlen($ss2)) {
    $longer = $ss2;
    $shorter = $ss1;
  }
  $longerLength = strlen($longer);
  if ($longerLength == 0) {
    return 1.0;
  }
  return ($longerLength - editDistance($longer, $shorter)) / floatval($longerLength);
}

function editDistance($s1, $s2) {
  $s1 = strtolower($s1);
  $s2 = strtolower($s2);

  $costs = [];
  for ($i = 0; $i <= strlen($s1); $i++) {
    $lastValue = $i;
    for ($j = 0; $j <= strlen($s2); $j++) {
      if ($i == 0)
        $costs[$j] = $j;
      else {
        if ($j > 0) {
          $newValue = $costs[$j - 1];
          if (substr($s1, $i - 1, 1) != substr($s2, $j - 1, 1))
            $newValue = min(min($newValue, $lastValue), $costs[$j]) + 1;
          $costs[$j - 1] = $lastValue;
          $lastValue = $newValue;
        }
      }
    }
    if ($i > 0)
      $costs[strlen($s2)] = $lastValue;
  }
  return $costs[strlen($s2)];
}
?>