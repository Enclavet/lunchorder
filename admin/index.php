<HTML>
<HEAD>
<link rel="stylesheet" href="../css/jquery-ui.min.css">
<script type="text/javascript" src="../js/jquery-3.1.1.min.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>

<style>
   #lunchorder_past_order {
     width: 700px;
     border-collapse: collapse;
   }
   #lunchorder_past_order td,th {
     font-size: 14px;
     border: 1px solid black;
     padding: 5px;
   }

   #lunchorder_edit_message table {
     width: 600px;
     border-collapse: collapse;
   }   
   #lunchorder_edit_message td,th {
     font-size: 13px;
     border: 1px solid black;
     padding: 5px;
   }
</style>

<script>
$(function() {
    $("#order_date").datepicker({
      dateFormat: "yy-mm-dd"
	  });
    get_order_list();
});

function submit_order() {
  var order_name=encodeURI($('#order_name').val());
  var order_url=encodeURI($('#order_url').val());
  var order_date=encodeURI($('#order_date').val());
  var family_style=$('#family_style').is(':checked');
  var lock=$('#lock').is(':checked');
  var order_list=$('#order_list').val();
  order_list=order_list.replace(/\n/g,",");
  var order_list_arr=order_list.split(",");
  var clean_order_list_arr = [];
  for (var i in order_list_arr) {
    if(isValidEmailAddress(order_list_arr[i])) {
      clean_order_list_arr.push(order_list_arr[i]);
    }
  }
  order_list=clean_order_list_arr.join(",");
  order_list=encodeURI(order_list);
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "order_name="+order_name+"&order_url="+order_url+"&order_date="+order_date+"&emaillist="+order_list+"&familystyle="+family_style+"&lock="+lock,
	success: function(data){
	if(data.result.error == 0) {
	  $("#order_name").val("");
	  $("#order_url").val("");
	  $("#order_date").val("");
	  $("#order_list").val("");
	} else {
	  alert(data.result.message);
	}
	get_order_list();
      }
    });
}

function copy_order(order_id) {
    $.ajax({
      url: "../handler.php",
	  type: "POST",
	  dataType: "json",
	  data: "view_order="+order_id,
	  success: function(data){
	   if(data.result.error == 0) {
	     var html="";
	     for(var i in data.result.smallorders) {
	       html += data.result.smallorders[i].email+"\n";
	     }
	     $('#order_list').val(html);
	   } else {
	     alert(data.result.message);
	   }
	}
      });
}

function edit_order(order_id) {
    $.ajax({
      url: "../handler.php",
	  type: "POST",
	  dataType: "json",
	  data: "view_order="+order_id,
	  success: function(data){
	   if(data.result.error == 0) {
	     var html = "";
	     html += "<h3>Order Details</h3>";
	     html += "<table>";
	     html += "<tr><td>Order ID:</td><td>"+data.result.order_details[0].order_id+"</td></tr>";
	     html += "<tr><td>Name:</td><td>"+data.result.order_details[0].name+"</td></tr>";
	     html += "<tr><td>URL:</td><td>"+data.result.order_details[0].url+"</td></tr>";
	     html += "<tr><td>Date:</td><td>"+data.result.order_details[0].date+"</td></tr>";
	     html += "<tr><td>Family Style:</td><td>"+data.result.order_details[0].familystyle+"</td></tr>";
	     html += "<tr><td>Lock:</td><td>"+data.result.order_details[0].lockstatus+"</td></tr>";
	     html += "</table>";

	     html += "<br><input type=\"button\" value=\"(Un)Lock Order\" onclick=\"toggle_lock('"+order_id+"')\" />&nbsp;<input type=\"button\" value=\"Send Reminder Email\" onclick=\"remind_email('"+order_id+"')\" />&nbsp;<input type=\"button\" value=\"Payment Reminder\" onclick=\"pay_remind_email('"+order_id+"')\" />&nbsp;<input type=\"button\" value=\"Add Email(s)\" onclick=\"add_email('"+order_id+"')\" />";

	     html += "<h3>Orders List</h3>";
	     html += "<table>";
	     html += "<tr><th>Email</th><th>Order Desc</th><th>Price</th><th>Opt Out</th><th>Status</th><th>Function</th></tr>";
	     var total = 0;
	     var count = 0;
	     for(var i in data.result.smallorders) {
	       html += "<tr><td>"+data.result.smallorders[i].email+"</td><td>"+data.result.smallorders[i].order_desc+"</td><td>"+(parseFloat(data.result.smallorders[i].price)*1.2).toFixed(2)+"</td><td>"+data.result.smallorders[i].optout+"</td><td>"+data.result.smallorders[i].status+"</td><td><a href=\"javascript:toggle_paid('"+data.result.smallorders[i].smallorder_id+"','"+order_id+"')\">Toggle Paid</a></td></tr>";
	       if(data.result.smallorders[i].optout == "false") {
		 total += parseFloat(data.result.smallorders[i].price)*1.2;
		 count++;
	       }
	     }
	     html += "</table>";
	     html += "<br>Total: $"+total.toFixed(2);
	     if(data.result.order_details[0].familystyle == "true") {
	       html += "<br><b>Family Style Price: "+(total/count).toFixed(2)+"</b>"; 
	     }
	     $( "#lunchorder_edit_message" ).html(html);
	     $( "#lunchorder_edit_message" ).dialog({
	           modal: true,
		   minWidth: 700,
		   buttons: {
		 Ok: function() {
		     $( this ).dialog( "close" );
		   }
		 }
	       });
	   } else {
	     alert(data.result.message);
	   }
	 }
       });
}

function toggle_paid(smallorder_id, order_id) {
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "toggle_status="+smallorder_id,
	success: function(data){
	if(data.result.error == 0) {
	} else {
	  alert(data.result.message);
	}
	edit_order(order_id);
      }
    });
}

function toggle_lock(order_id) {
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "toggle_lock="+order_id,
	success: function(data){
	if(data.result.error == 0) {
	} else {
	  alert(data.result.message);
	}
	edit_order(order_id);
      }
    });
}

function get_smallorder_list() {

}

function delete_order(order_id) {
     $.ajax({
      url: "../handler.php",
	  type: "POST",
	  dataType: "json",
	  data: "delete_order="+order_id,
	  success: function(data){
	   if(data.result.error == 0) {
	   } else {
	     alert(data.result.message);
	   }
	   get_order_list();
	 }
       });
}

function get_order_list() {
    $.ajax({
      url: "../handler.php",
	  type: "POST",
	  dataType: "json",
	  data: "get_order_list=1",
	  success: function(data){
	  if(data.result.error == 0) {
	    var html = "<table id=\"lunchorder_past_order\">";
	    html += "<tr>";
	    html += "<th>Order ID</td>";
	    html += "<th>Name</td>";
	    html += "<th>URL</td>";
	    html += "<th>Date</td>";
	    html += "<th>Functions</td>";
	    html += "</tr>";
	    for(var i in data.result.order_list) {
	      html += "<tr>";
	      html += "<td>"+data.result.order_list[i].order_id+"</td>";
	      html += "<td>"+data.result.order_list[i].name+"</td>";
	      html += "<td><A HREF='"+data.result.order_list[i].url+"'>"+data.result.order_list[i].url+"</A></td>";
	      html += "<td>"+data.result.order_list[i].date+"</td>";
	      html += "<td><a href=\"javascript:edit_order('"+data.result.order_list[i].order_id+"');\">View</a> <a href=\"javascript:delete_order('"+data.result.order_list[i].order_id+"');\">Delete</a> <a href=\"javascript:copy_order('"+data.result.order_list[i].order_id+"');\">Copy</a></td>";
	      html += "</tr>";
	    }
	    html += "</table>";
	    $('#past_order_list').html(html);
	  } else {
	    alert(data.message);
	  }
	}
      }
      );
}

function pay_remind_email(order_id) {
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "pay_remind_email="+order_id,
	success: function(data){
	if(data.result.error == 0) {
	} else {
	  alert(data.result.message);
	}
      }
    });
}

function remind_email(order_id) {
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "remind_email="+order_id,
	success: function(data){
	if(data.result.error == 0) {
	} else {
	  alert(data.result.message);
	}
      }
    });
}

function add_email(order_id) {
  var html = "";
  html += "Email List:<br><textarea rows=\"10\" cols=\"40\" name=\"email_list\" id=\"email_list\"></textarea>";

  $( "#lunchorder_add_message" ).html(html);
  $( "#lunchorder_add_message" ).dialog({
    modal: true,
	minWidth: 350,
	buttons: {
      Ok: function() {
	  add_email_ajax(order_id);
	  $( this ).dialog( "close" );
	  edit_order(order_id);
	},
      Cancel: function() {
	  $( this ).dialog( "close" );
	}
      }
    });
}

function add_email_ajax(order_id) {
  var order_list=$('#email_list').val();
  order_list=order_list.replace(/\n/g,",");
  var order_list_arr=order_list.split(",");
  var clean_order_list_arr = [];
  for (var i in order_list_arr) {
    if(isValidEmailAddress(order_list_arr[i])) {
      clean_order_list_arr.push(order_list_arr[i]);
    }
  }
  order_list=clean_order_list_arr.join(",");
  order_list=encodeURI(order_list);
  $.ajax({
    url: "../handler.php",
	type: "POST",
	dataType: "json",
	data: "add_email="+order_list+"&order_id="+order_id,
	success: function(data){
	if(data.result.error == 0) {
	} else {
	  alert(data.result.message);
	}
      }
    });
}

function isValidEmailAddress(emailAddress) {
    var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
    return pattern.test(emailAddress);
};

</script>
</HEAD>

<BODY>

<div id="lunchorder_edit_message" title="View Order"></div>
<div id="lunchorder_add_message" title="Add Email"></div>

<h1>Create Order</h1>
<form>
<table>
<tr><td>Restaurant Name:</td><td><input type="text" name="order_name" id="order_name"/></td></tr>
<tr><td>Menu URL:</td><td><input type="text" size="40" name="order_url" id="order_url"/></td></tr>
<tr><td>Family Style:</td><td><input type="checkbox" name="family_style" id="family_style"/></td></tr>
<tr><td>Date:</td><td><input type="text" size="10" name="order_date" id="order_date"/></td></tr>
<tr><td>Email List:</td><td><textarea rows="10" cols="40" name="order_list" id="order_list"></textarea></td></tr>
<tr><td colspan="2"><input type="button" value="Submit" onclick="submit_order();"></td></tr>
</table>
</form>

<h1>Past Orders</h1>
<div id="past_order_list">

</div>
<?php

?>

</BODY>

</HTML>
