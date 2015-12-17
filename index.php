<?php
if (isset($_POST['submit'])) {
//echo '<pre>';print_r($_FILES);echo '</pre>';exit;
    if ($_FILES['xmldata']['error']) {
        $error = 'Error code:' . $_FILES['xmldata']['error'];
    }
    if ($error == '' && $_FILES['xmldata']['type'] != 'text/xml') {
        $error = 'Невірний формат файлу';
    }
    $srcFolder = 'src/' . date('Y.m.d_H-i-s', strtotime('now'));
    if (!is_dir($srcFolder)) {
        mkdir($srcFolder);
        chmod($srcFolder, 0775);
    }
    if ($error == '') {
        include 'functions.php';
        $data = file_get_contents($_FILES['xmldata']['tmp_name']);
        $data_arr = xml2array($data);
        $fields = array();
        ob_start();
        if (isset($data_arr['RECORDS']['record']['0'])) {
            $records = $data_arr['RECORDS']['record'];
        } else {
            $records = $data_arr['RECORDS'];
        }
        foreach ($records as $key => $field) {
            $item = new IrbisExport($field, $srcFolder);
            //echo '<pre>';print_r($item->getRecord());echo '</pre>';
            //$item->delSrc($key+1);
            //echo $item->getBibOpys().'<br/><br/>';
            if (!$item->createSrc($key + 1)) {
                echo 'Помилка створення файлів для запису ' . $item->getRecordName();
            }
            unset($item);
        }
        $buffer = ob_get_contents();
        ob_end_clean();
        if ($buffer) {
            $error = 'Помилки під час роботи:<br/><div style="background:#CCDD48;">' . $buffer . '</div>';
        } else {
            $message = '<b>Матеріали для імпорту згенеровано!</b><br/> Щоб провести імпорт скопіюйте файли з папки ' . $_SERVER['DOCUMENT_ROOT'] . '/dspace-exim/' . $srcFolder . ' в папку імпорту на сервері Dspace C:/Distr/DspaceImport/importsrc';
        }
    }
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
  "http://www.w3.org/TR/html4/strict.dtd">

<html>
<head>
  <meta http-equiv="Content-Language" content="ru">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Програма завантаження даних з системи Irbis та формування файлів для
    імпорту у систему Dspace</title>
  <link rel="stylesheet" href="style.css" type="text/css">
</head>
<body>
  <div class="header">
    <div class="adminMenu">
      <h1>Програма завантаження даних з системи Irbis та формування файлів для
        імпорту у систему Dspace</h1>
    </div>
  </div>
  <div class="wraper">
    <div class="content">
      <?php if ($error != '') { ?>
        <div style="color:red">
          <?php print $error; ?>
        </div>
        <a href="https://docs.google.com/a/scbali.com/spreadsheet/viewform?formkey=dG1nLUlFc1lyUmhDR2FOZEVad05Mamc6MQ" target="_blank">Повідомити про помилку</a>
      <?php }
      if ($message != '') { ?>
        <div>
          <?php print $message; ?>
        </div>
        <a href="index.php">Перейти до форми завантаження</a>
      <?php }
      else { ?>
      <form action="" method="POST" enctype="multipart/form-data">
        Вкажіть файл експортований з Irbis: <input type="file" name="xmldata"/><br/>
        <input type="submit" name="submit" value="Отправить">
        <?php } ?>
    </div>
  </div>

  <div class="footer">
    <span class="copyright">&copy; <a href="http://deweb.com.ua">deWeb.com.ua</a></span>
  </div>
</body>
</html>
