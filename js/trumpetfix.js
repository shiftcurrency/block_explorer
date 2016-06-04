window.setTimeout(function(){
  if($("#trumpet_message").length > 0 && $("#trumpet_message").html() != ''){
    $("body").css('padding-top','25px');
  }
},1000);  
$("#trumpet_message").click(function(){$("body").css('padding-top','0');});
