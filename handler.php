<?php
require_once(realpath(dirname(__FILE__) . "/config.php"));
date_default_timezone_set($config['timezone']);

// Create connection
$conn = new mysqli($config['db']['server'], $config['db']['user'], $config['db']['pass'], $config['db']['dbname']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

if(isset($_POST['order_name'])) {
  $error=0;
  $order_name=urldecode($_POST['order_name']);
  $order_url=urldecode($_POST['order_url']);
  $order_date=urldecode($_POST['order_date']);
  $emaillist=urldecode($_POST['emaillist']);
  $family_style=urldecode($_POST['familystyle']);
  $lock=urldecode($_POST['lock']);
  $sql = "INSERT INTO mainorder (name, url, date, emaillist, familystyle, lockstatus) VALUES ('$order_name', '$order_url', '$order_date', '$emaillist', '$family_style', '$lock')";
  if ($conn->query($sql) !== TRUE) { $error=1; }

  $lastid=$conn->insert_id;

  $emails = explode(',',$emaillist);
  foreach($emails as $key) {
    if($key) {
      $sql = "INSERT INTO smallorder (order_id, email, order_desc, price, status, optout) VALUES ('".$lastid."', '$key', '', '', 'Not Paid', 'false');";
      if ($conn->query($sql) !== TRUE) { $error=1; }
      $smallorder_lastid = $conn->insert_id;
      sendMail($smallorder_lastid, $conn);
    }
  }
  
  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_GET['set_optout'])) {
  $error=0;
  $sql = "UPDATE smallorder SET optout='true' WHERE smallorder_id='".$_GET['set_optout']."'";
  if ($conn->query($sql) !== TRUE) { $error=1; }
  if ($error == 0) {
    echo "Opted out succesfully!";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['add_email'])) {
  $emaillist = urldecode($_POST['add_email']);
  $order_id = urldecode($_POST['order_id']);
  $error=0;
  $emails = explode(',',$emaillist);
  foreach($emails as $key) {
    if($key) {
      $sql = "INSERT INTO smallorder (order_id, email, order_desc, price, status, optout) VALUES ('".$order_id."', '$key', '', '', 'Not Paid', 'false');";
      if ($conn->query($sql) !== TRUE) { $error=1; }
      $smallorder_lastid = $conn->insert_id;
      sendMail($smallorder_lastid, $conn);
    }
  }

  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['get_order_list'])) {
  $sql = "SELECT order_id,name,url,date,emaillist,familystyle,lockstatus FROM mainorder";
  $result = $conn->query($sql);
  $rows = array();

  if ($result) {
    while($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    echo "{\"result\":{\"error\":0,\"order_list\":";
    echo json_encode($rows);
    echo "}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['delete_order'])) {
  $error=0;
  $sql = "DELETE FROM smallorder WHERE order_id='".$_POST['delete_order']."'";
  if ($conn->query($sql) !== TRUE) { $error=1; }
  $sql = "DELETE FROM mainorder WHERE order_id='".$_POST['delete_order']."'";
  if ($conn->query($sql) !== TRUE) { $error=1; }

  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['toggle_status'])) {
  $error=0;
  $status = "";
  $sql = "SELECT status FROM smallorder WHERE smallorder_id='".$_POST['toggle_status']."'";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  if($row['status'] == "Not Paid") {
    $status = "Paid";
  } else {
    $status = "Not Paid";
  }

  $sql = "UPDATE smallorder SET status='".$status."' WHERE smallorder_id='".$_POST['toggle_status']."'";
  if ($conn->query($sql) !== TRUE) { $error=1; }

  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['toggle_lock'])) {
  $error=0;
  $status = "";
  $sql = "SELECT familystyle,lockstatus FROM mainorder WHERE order_id='".$_POST['toggle_lock']."'";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  $familystyle = $row['familystyle'];
  if($row['lockstatus'] == "true") {
    $status = "false";
  } else {
    $status = "true";
  }

  $sql = "UPDATE mainorder SET lockstatus='".$status."' WHERE order_id='".$_POST['toggle_lock']."'";
  if ($conn->query($sql) !== TRUE) { $error=1; }
  if($status = "true") {
    $sql = "SELECT smallorder_id,SUM(price) as total, COUNT(*) as count FROM smallorder WHERE order_desc != '' AND optout != 'true' AND status = 'Not Paid' AND order_id = '".$_POST['toggle_lock']."'";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
      sendMailPayment($row['smallorder_id'],$conn,$row['total'],$row['count']);
    }
  }
    

  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['view_order'])) {
  $sql = "SELECT order_id,name,url,date,familystyle,lockstatus FROM mainorder WHERE order_id='".$_POST['view_order']."'";
  $result = $conn->query($sql);
  $sql = "SELECT smallorder_id,email,order_desc,price,status,optout FROM smallorder WHERE order_id='".$_POST['view_order']."' ORDER BY optout,order_desc DESC";
  $result2 = $conn->query($sql);
  $rows = array();
  $rows2 = array();

  if ($result) {
    while($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }

    while($row2 = $result2->fetch_assoc()) {
      $rows2[] = $row2;
    }

    echo "{\"result\":{\"error\":0,\"order_details\":";
    echo json_encode($rows);
    echo ",\"smallorders\":";
    echo json_encode($rows2);
    echo "}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['make_smallorder'])) {
  $error=0;
  $order_desc = mysqli_real_escape_string($conn, preg_replace("/[^A-Za-z0-9 ]/",'',urldecode($_POST['order_desc'])));
  $sql = "UPDATE smallorder SET order_desc='".$order_desc."',price='".urldecode($_POST['price'])."',optout='".urldecode($_POST['optout'])."' WHERE smallorder_id='".$_POST['make_smallorder']."';";
  if ($conn->query($sql) !== TRUE) { $error=1; }

  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['get_familystyle_price'])) {
  $error=0;
  $sql = "SELECT SUM(price) as total, COUNT(*) as count FROM smallorder WHERE order_id=".$_POST['get_familystyle_price']." AND optout = 'false'";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  if ($error == 0) {
    echo "{\"result\":{\"error\":0, \"price\":".$row['total'].", \"count\":".$row['count']."}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['remind_email'])) {
  $error=0;
  $sql = "SELECT smallorder_id FROM smallorder WHERE order_desc = '' AND optout != 'true' AND order_id = '".$_POST['remind_email']."'";
  $result = $conn->query($sql);
  while($row = $result->fetch_assoc()) {
    sendMail($row['smallorder_id'],$conn);
  }
  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

if(isset($_POST['pay_remind_email'])) {
  $error=0;
  $sql = "SELECT smallorder_id,SUM(price) as total, COUNT(*) as count FROM smallorder WHERE order_desc != '' AND optout != 'true' AND status = 'Not Paid' AND order_id = '".$_POST['pay_remind_email']."'";
  $result = $conn->query($sql);
  while($row = $result->fetch_assoc()) {
    sendMailPayment($row['smallorder_id'],$conn,$row['total'],$row['count']);
  }
  if ($error == 0) {
    echo "{\"result\":{\"error\":0}}";
  } else {
    echo "{\"result\":{\"error\":1,\"message\":\"".$conn->error."\"}}";
  }
}

function sendMailPayment($smallorder_id, $conn,$total,$count) {
  global $config;

  $sql = "SELECT mainorder.name as name,mainorder.url as url,mainorder.date as date,mainorder.familystyle as familystyle,smallorder.email as email,smallorder.order_desc as order_desc,smallorder.price as price FROM smallorder INNER JOIN mainorder ON smallorder.order_id = mainorder.order_id WHERE smallorder.smallorder_id='".$smallorder_id."';";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  if($row['familystyle'] != "true") {
    $price = number_format((float)$row['price'], 2, '.', '');
    $tax = number_format((float)$price * 0.10, 2, '.', '');
    $service = number_format((float)$price * 0.10, 2, '.', '');
    $total = number_format((float)($price+$tax+$service), 2, '.', '');
  } else {
    $price = number_format((float)($total/$count), 2, '.', '');
    $tax = number_format((float)$price * 0.10, 2, '.', '');
    $service = number_format((float)$price * 0.10, 2, '.', '');
    $total = number_format((float)($price+$tax+$service), 2, '.', '');
  }

  $dayOf = strtoupper(date("D", strtotime($row['date'])));

  $boundary = sha1(date('r', time()));

  $mailheader = "From: " . $config['email']['from'] . "\r\n" .
    "Mime-Version: 1.0\r\n" .
    "Reply-To: " . $config['email']['from'] . "\r\n";

  $mailheader .= "X-Mailer: PHP/" . phpversion() . "\r\n" .
    "Content-Type: multipart/mixed; boundary=\"PHP-mixed-{$boundary}\"\r\n";

  $message = "--PHP-mixed-{$boundary}\n" .
    "Content-Type: multipart/alternative; boundary=PHP-alt-{$boundary}\n\n" .
    "--PHP-alt-{$boundary}\n" .
    "Content-Type: text/plain\n" .
    "To view message you need to enable HTML.\n\n" .
    "--PHP-alt-{$boundary}\n" .
    "Content-Type: multipart/related; boundary=PHP-related-{$boundary}\n\n" .
    "--PHP-related-{$boundary}\n" .
    "Content-Type: text/html; charset=utf-8\n\n" .
    "<HTML>\n" .
    "<HEAD><TITLE>Lunch Order</TITLE>\n" .
    "<BODY>" .
    "<CENTER>\n" .
    "<TABLE cellspacing='0' cellpadding='10' border='0'>\n" .
    "<TR><TD width='540'>\n" .
    "<IMG SRC=\"cid:emaillogo.png@" . $config['baseUrl'] . "\" ALT='Email Logo'>\n" .
    "</TD></TR>\n" .
    "<TR><TD width='540'>\n" .
    "<H3>This is a Payment reminder.</H3>\n" .
    "</TD></TR>\n" .
    "<TR><TD width='540' align='center'>\n" .
    "<table width='300' height='120px' bgcolor='#e4e4e4' \n" .
    "style='text-align: center; margin: 0 auto; border-radius: 4px;' \n" .
    "cellspacing='0' cellpadding='15' border='0'>\n" .
    "<TR><TD>\n" .
    "<DIV style='box-shadow: 0px 2px 14px 6px #CCC;'>\n" .
    "<h3>Amount Due</h3>\n" .
    "<table>\n" .
    "<tr><td>Price</td><td>$".$price."</td></tr>\n" .
    "<tr><td>Tax (10%)</td><td>$".$tax."</td></tr>\n" .
    "<tr><td>Service (10%)</td><td>$".$service."</td></tr>\n" .
    "<tr><td>Total</td><td>$".$total."</td></tr>\n" .
    "<tr><td colspan=\"2\"><a class=\"ui-button ui-widget ui-corner-all\" href=\"" . $config[paypal] . "/".$total."\">Pay with Paypal</a></td></tr>\n" .
    "<tr><td colspan=\"2\"><a class=\"ui-button ui-widget ui-corner-all\" href=\"" . $config[venmo] . "&amount=".$total."\">Pay with Venmo</a></td></tr>\n" .
    "<tr><td colspan=\"2\">Or you can pay me in person with cash.</td></tr>\n" .
    "</table>\n" .
    "</DIV>\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "</TD></TR>\n" .
    "<TR><TD align='center'>\n" .
    "<H2 style='font-weight: normal; margin-top:20px'>We're ordering from \n" .
    "<B><A HREF='" . $row['url'] . "'>" . $row['name'] . "</A></B></H2>\n" .
    "</TD></TR>\n" .
    "<TR><TD align='center'>\n" .
    "<A HREF='https://" . $config['baseUrl'] . "/user/user.php?smallorderid=\n" .
    $smallorder_id . "' style=\"\n" .
    "display: block;\n" .
    "background: #be1840;\n" .
    "width: 220px;\n" .
    "color: white;\n" .
    "text-decoration: none;\n" .
    "text-align: center;\n" .
    "min-height: 55px;\n" .
    "border-radius: 7px;\n" .
    "margin-top: 10px;\n" .
    "margin-bottom: 15px;\n" .
    "margin-left: 165px;\n" .
    "\">\n" .
    "<SPAN style='line-height: 55px;'>VIEW/CHANGE YOUR ORDER</SPAN>\n" .
    "</A>\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "</DIV>\n" .
    "</TD>\n" .
    "</TR>\n" .
    "</TABLE>\n" .
    "</CENTER>\n" .
 
    "</BODY></HTML>";

  $emaillogo = chunk_split(base64_encode(file_get_contents($config['paths']['images']['emaillogo'])));
  $message .= "\n--PHP-related-{$boundary}\n" .
    "Content-Type: image/png; name=\"emaillogo.png\"\n" .
    "Content-ID: <emaillogo.png@" . $config['baseUrl'] . ">\n" .
    "Content-Disposition: attachment; filename=\"emaillogo.png\"\n" .
    "Content-Transfer-Encoding: base64\n\n" .
    $emaillogo . "\n" .
    "--PHP-related-{$boundary}--\n\n" .
    "--PHP-alt-{$boundary}--\n\n" .
    "--PHP-mixed-{$boundary}--\n";


   $subject = "Payment Reminder for ".$row['name']." on ".$dayOf." ".$row['date'];
   $subject = convert_smart_quotes($subject);
   $message = convert_smart_quotes($message);

   mail($row['email'], $subject, $message, $mailheader);
}

function sendMail($smallorder_id, $conn) {
  global $config;

  $sql = "SELECT mainorder.name as name,mainorder.url as url,mainorder.date as date,smallorder.email as email,smallorder.order_desc as order_desc,smallorder.price as price FROM smallorder INNER JOIN mainorder ON smallorder.order_id = mainorder.order_id WHERE smallorder.smallorder_id='".$smallorder_id."';";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();

  $dayOf = strtoupper(date("D", strtotime($row['date'])));

  $boundary = sha1(date('r', time()));

  $mailheader = "From: " . $config['email']['from'] . "\r\n" .
    "Mime-Version: 1.0\r\n" .
    "Reply-To: " . $config['email']['from'] . "\r\n";

  $mailheader .= "X-Mailer: PHP/" . phpversion() . "\r\n" .
    "Content-Type: multipart/mixed; boundary=\"PHP-mixed-{$boundary}\"\r\n";

  $message = "--PHP-mixed-{$boundary}\n" .
    "Content-Type: multipart/alternative; boundary=PHP-alt-{$boundary}\n\n" .
    "--PHP-alt-{$boundary}\n" .
    "Content-Type: text/plain\n" .
    "To view message you need to enable HTML.\n\n" .
    "--PHP-alt-{$boundary}\n" .
    "Content-Type: multipart/related; boundary=PHP-related-{$boundary}\n\n" .
    "--PHP-related-{$boundary}\n" .
    "Content-Type: text/html; charset=utf-8\n\n" .
    "<HTML>\n" .
    "<HEAD><TITLE>Lunch Order</TITLE>\n" .
    "<BODY>" .
    "<CENTER>\n" .
    "<TABLE cellspacing='0' cellpadding='10' border='0'>\n" .
    "<TR><TD width='540'>\n" .
    "<IMG SRC=\"cid:emaillogo.png@" . $config['baseUrl'] . "\" ALT='Email Logo'>\n" .
    "</TD></TR>\n" .
    "<TR><TD width='540'>\n" .
    "<H3>If you want to join the group order, follow the link to place your order</H3>\n" .
    "</TD></TR>\n" .
    "<TR><TD width='540' align='center'>\n" .
    "<table width='300' height='120px' bgcolor='#e4e4e4' \n" .
    "style='text-align: center; margin: 0 auto; border-radius: 4px;' \n" .
    "cellspacing='0' cellpadding='15' border='0'>\n" .
    "<TR><TD>\n" .
    "<DIV style='box-shadow: 0px 2px 14px 6px #CCC;'>\n" .
    "<TABLE cellspacing='0' cellpadding='5' style='text-align: center; color: #fff' width='100%'>\n" .
    "<TR><TD bgcolor='#BE1840'>\n" .
    "ORDER BY:\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "<TABLE bgcolor='#fff' cellspacing='10' style='text-align: center;' width='100%'>\n" .
    "<TR><TD>\n" .
    "<DIV style='font-size: 20px'>" . $row['date'] . "</DIV>\n" .
    "</TD></TR>\n" .
    "<TR><TD>10:30 AM</TD></TR>\n" .
    "</TABLE></DIV></TD>\n" .
    "<TD><DIV style='box-shadow: 0px 2px 14px 6px #CCC;'>\n" .
    "<TABLE cellspacing='0' cellpadding='5' style='text-align: center; color: #fff' width='100%'>\n" .
    "<TR><TD bgcolor='#59C2C1'>\n" .
    "ENJOY ON:\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "<TABLE bgcolor='#fff' cellspacing='10' style='text-align: center;' width='100%'>\n" .
    "<TR><TD>\n" .
    "<DIV style='font-size: 20px'>" . $row['date'] . "</DIV>\n" .
    "</TD></TR>\n" .
    "<TR><TD>\n" .
    "11:30 AM\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "</DIV>\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "</TD></TR>\n" .
    "<TR><TD align='center'>\n" .
    "<H2 style='font-weight: normal; margin-top:20px'>We're ordering from \n" .
    "<B><A HREF='" . $row['url'] . "'>" . $row['name'] . "</A></B></H2>\n" .
    "</TD></TR>\n" .
    "<TR><TD align='center'>\n" .
    "<A HREF='https://" . $config['baseUrl'] . "/user/user.php?smallorderid=\n" .
    $smallorder_id . "' style=\"\n" .
    "display: block;\n" .
    "background: #be1840;\n" .
    "width: 220px;\n" .
    "color: white;\n" .
    "text-decoration: none;\n" .
    "text-align: center;\n" .
    "min-height: 55px;\n" .
    "border-radius: 7px;\n" .
    "margin-top: 10px;\n" .
    "margin-bottom: 15px;\n" .
    "margin-left: 165px;\n" .
    "\">\n" .
    "<SPAN style='line-height: 55px;'>PLACE YOUR ORDER</SPAN>\n" .
    "</A>\n" .
    "</TD></TR>\n" .
    "<TR><TD align='center'>\n" .
    "<A HREF='https://" . $config['baseUrl'] . "/handler.php?set_optout=\n" .
    $smallorder_id . "' style=\"\n" .
    "color: #10B9B9;\n" .
    "display: block;\n" .
    "font-size: 12px;\n" .
    "margin-bottom: 20px;\n" .
    "text-decoration: none;\n" .
    "\">\n" .
    "I'm not eating \n" .
    "</A>\n" .
    "</TD></TR>\n" .
    "</TABLE>\n" .
    "</DIV>\n" .
    "</TD>\n" .
    "</TR>\n" .
    "</TABLE>\n" .
    "</CENTER>\n" .
 
    "</BODY></HTML>";

  $emaillogo = chunk_split(base64_encode(file_get_contents("images/emaillogo.png")));
  $message .= "\n--PHP-related-{$boundary}\n" .
    "Content-Type: image/png; name=\"emaillogo.png\"\n" .
    "Content-ID: <emaillogo.png@" . $config['baseUrl'] . ">\n" .
    "Content-Disposition: attachment; filename=\"emaillogo.png\"\n" .
    "Content-Transfer-Encoding: base64\n\n" .
    $emaillogo . "\n" .
    "--PHP-related-{$boundary}--\n\n" .
    "--PHP-alt-{$boundary}--\n\n" .
    "--PHP-mixed-{$boundary}--\n";


   $subject = "Lunch Order for ".$row['name']." on ".$dayOf." ".$row['date'];

   $subject = convert_smart_quotes($subject);
   $message = convert_smart_quotes($message);

   mail($row['email'], $subject, $message, $mailheader);
}

function convert_smart_quotes($string) {
  global $config;

   $chr_map = array(
      // Windows codepage 1252
      "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
      "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
      "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
      "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
      "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
      "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
      "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
      "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
      "\xC2\x96" => "-", // U+0096⇒U+2013 En Dash
      "\xC2\x97" => "-", // U+0097⇒U+2014 Em Dash

      // Regular Unicode     // U+0022 quotation mark (")
                             // U+0027 apostrophe     (')
      "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
      "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
      "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
      "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
      "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
      "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
      "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
      "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
      "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
      "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
      "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
      "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
      "\xE2\x80\x93" => "-", // U+2013 En Dash
      "\xE2\x80\x94" => "-", // U+2014 Em Dash
   );

   $chr = array_keys  ($chr_map); // but: for efficiency you should
   $rpl = array_values($chr_map); // pre-calculate these two arrays
   return str_replace($chr, $rpl, $string);

}

$conn->close();

?>
