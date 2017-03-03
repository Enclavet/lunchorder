<?php
define('__ROOT__', dirname(dirname(__FILE__))); 
require_once(__ROOT__.'/config.php'); 
?> 
<HTML>

<HEAD>
<link rel="stylesheet" href="../css/jquery-ui.min.css">
<script type="text/javascript" src="../js/jquery-3.1.1.min.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>

<style>
   #lunchorder_place_order {
     border-collapse: collapse;
   }
   #lunchorder_place_order td,th {
     padding: 5px;
   }
   #lunchorder_order_due {
     border-collapse: collapse;
   }
   #lunchorder_order_due td,th {
     padding: 5px;
   }
   #lunchorder_order_list table {
     width: 600px;
     border-collapse: collapse;
   }   
   #lunchorder_order_list td,th {
     font-size: 13px;
     border: 1px solid black;
     padding: 5px;
   }
</style>

<script>
function show_smallorders(order_id) {
    $.ajax({
      url: "/handler.php",
	  type: "POST",
	  dataType: "json",
	  data: "view_order="+order_id,
	  success: function(data){
	   if(data.result.error == 0) {
	     var html = "";
	     html += "<table>";
	     html += "<tr><th>Email</th><th>Order Desc</th><th>Menu Price</th></tr>";
	     var total = 0;
	     var count = 0;
	     for(var i in data.result.smallorders) {
	       html += "<tr><td>"+data.result.smallorders[i].email+"</td><td>"+data.result.smallorders[i].order_desc+"</td><td>"+(parseFloat(data.result.smallorders[i].price)).toFixed(2)+"</td></tr>";
	       if(data.result.smallorders[i].optout == "false") {
		 total += parseFloat(data.result.smallorders[i].price)*1.2;
		 count++;
	       }
	     }
	     html += "</table>";
	     $('#lunchorder_order_list').html(html);
	   }
	}
      });
}

function make_smallorder(smallorder_id) {
  if($.isNumeric($('#order_price').val())) {
      var order_desc = encodeURI($('#order_desc').val());   
      var price = encodeURI($('#order_price').val());  
      if(price == "") {
	alert("Please put in the price listed in the menu");
      } else {
	var optout = $('#opt_out').is(':checked');  
	$.ajax({
	  url: "/handler.php",
	      type: "POST",
	      dataType: "json",
	      data: "make_smallorder="+smallorder_id+"&order_desc="+order_desc+"&price="+price+"&optout="+optout,
	      success: function(data){
	      if(data.result.error == 0) {
		alert("Order Submitted Succesfully");
		order_due();
	      } else {
		alert(data.result.message);
	      }
	    }
	  });
      }
  } else {
      alert("Price must be numeric");
  }
}

function draw_order_due(price) {
  price = parseFloat(price);
  var tax = price*.10;
  var service = price*.10;
  var total = price+tax+service;
  var html = "";
  html += "<h3>Amount Due</h3>";
  html += "<table>";
  html += "<tr><td>Price</td><td>$"+price.toFixed(2)+"</td></tr>";
  html += "<tr><td>Tax (10%)</td><td>$"+tax.toFixed(2)+"</td></tr>";
  html += "<tr><td>Service (10%)</td><td>$"+service.toFixed(2)+"</td></tr>";
  html += "<tr><td>Total</td><td>$"+total.toFixed(2)+"</td></tr>";

  html += "<tr><td colspan=\"2\"><a class=\"ui-button ui-widget ui-corner-all\" href=\"<?php echo $config[paypal] ?>/"+total.toFixed(2)+"\">Pay with Paypal</a></td></tr>";
  html += "<tr><td colspan=\"2\"><a class=\"ui-button ui-widget ui-corner-all\" href=\"<?php echo $config[venmo] ?>&amount="+total.toFixed(2)+"\">Pay with Venmo</a></td></tr>";

  html += "</table>";
  $('#lunchorder_order_due').html(html);
  $( ".widget input[type=submit], .widget a, .widget button" ).button();
}

function order_due() {
  var price = 0;
  if(!$('#opt_out').is(':checked')) {
    if(familystyle == "false") {
      price = parseFloat($('#order_price').val());
      draw_order_due(price);
    } else {
      if(lockstatus != "false") {
	$.ajax({
	  url: "/handler.php",
	      type: "POST",
	      dataType: "json",
	      data: "get_familystyle_price="+order_id,
	      success: function(data){
	      if(data.result.error == 0) {
		var pricesplit = data.result.price/data.result.count;
		price = pricesplit.toFixed(2);
		draw_order_due(price);
	      } else {
		alert(data.result.message);
	      }
	    }
	  });
      }
    }
  } else {
    $('#lunchorder_order_due').html("");
  }
}


$(function() {
    order_due();
    show_smallorders(order_id);
    $( ".widget input[type=submit], .widget a, .widget button" ).button();
});
</script>

</HEAD>

<BODY>

<?php

date_default_timezone_set($config['timezone']);

$conn = new mysqli($config['db']['server'], $config['db']['user'], $config['db']['pass'], $config['db']['dbname']);
// Create connection

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

if(isset($_GET['smallorderid'])) {
  $sql = "SELECT mainorder.order_id as order_id,mainorder.name as name,mainorder.url as url,mainorder.date as date, mainorder.familystyle as familystyle, mainorder.lockstatus as lockstatus,smallorder.email as email,smallorder.order_desc as order_desc,smallorder.price as price,smallorder.optout as optout FROM smallorder INNER JOIN mainorder ON smallorder.order_id = mainorder.order_id WHERE smallorder.smallorder_id='".$_GET['smallorderid']."';";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();

  print "<h1>Lunch Order Rules</h1>";
  print "<ul>";
  print "<li>Do not order for someone else. Only order for yourself.</li>";
  print "<li>Do not add anyone to the group. Keep this manageable.</li>";
  print "<li>There is a 10% surcharge that will go the driver/wingman. This is just a starting point and can increase/decrease depending on the situation.</li>";
  print "<li>Orders must be in at 10:30AM the day of the lunch. Any orders after that will be ignored.</li>";
  print "<li>You can pay with Paypal or Cash. If using Cash, round up your payment to the nearest integer.</li>";
  print "<li>There is an honor system with the price. Any abuse will get you banned from the group.</li>";
  print "</ul>";

  print "<h1>Place Order</h1>";
  print "<table id=\"lunchorder_place_order\">";
  print "<tr><td>Restaurant Name</td><td>".$row['name']."</td></tr>";
  print "<tr><td>Menu URL</td><td><a href=\"".$row['url']."\" target=\"_blank\">".$row['url']."</a></td></tr>";
  print "<tr><td>Date</td><td>".$row['date']."</td></tr>";
  print "<tr><td>Email</td><td>".$row['email']."</td></tr>";
  print "<tr><td>Family Style</td><td>".$row['familystyle']."</td></tr>";
  print "<tr><td>Opt Out</td><td><input type=\"checkbox\" name=\"opt_out\" id=\"opt_out\" ";
  if($row['optout'] == "true") {
    print "checked";
  }
  print "/></td></tr>";
  print "<tr><td>Order Desc</td><td><input type=\"text\" value=\"".$row['order_desc']."\" name=\"order_desc\" id=\"order_desc\"/></td></tr>";
  print "<tr><td>Menu Price</td><td><input type=\"text\" value=\"".$row['price']."\" name=\"order_price\" id=\"order_price\"/></td></tr>";
  print "</table>";
  if($row['lockstatus'] != "true") {
    print "<input class=\"ui-button ui-widget ui-corner-all\" type=\"button\" value=\"Submit\" onclick=\"make_smallorder('".$_GET['smallorderid']."');\"/>";
  }
  print "<div id=\"lunchorder_order_due\">";
  print "</div>";
  print "<h1>What everyone else is ordering</h1>";
  print "<div id=\"lunchorder_order_list\">";
  print "</div>";

  print "<script>";
  print "var familystyle=\"".$row['familystyle']."\";";
  print "var order_id=\"".$row['order_id']."\";";
  print "var lockstatus=\"".$row['lockstatus']."\";";
  print "</script>";
}

?>
</BODY>
</HTML>

