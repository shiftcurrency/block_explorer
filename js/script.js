var customBrandcolor = '#009540';

$("#checkNodeSubmit").click(function(e){
  e.preventDefault();
  var loading = $("#checkNodeSubmit").busy();

  $.ajax({
    url: $("#checkNodeForm").attr("action"),
    type: "POST",
    data: $("#checkNodeForm").serialize(),
    success:function(data){
      $("#checkNodeDiv").html(data);
      loading.busy("hide");
    },
    error:function(){
      loading.busy("hide");
    }
  });
});

$("#checkNodesSubmit").click(function(e){
  e.preventDefault();
  var loading = $("#checkNodesSubmit").busy();
  $("#checkNodesForm").submit();
});

$(".SwitchCheckMode").click(function(e){
  e.preventDefault();
  $("#checkNodeForm").toggle();
  $("#checkNodesForm").toggle();
});

var refreshTimeout = 30000;
var refreshInterval = null;
var blockRefreshId = null;


function homeBlocks(){
  $.ajaxSetup({ cache:true });
  $.ajax({
    url:"/?page=homeBlocks",
    type:"GET",
    success:function(html){
      $("#homeBlocks").html(html);
    }
  });
}

function homeTransactions(){
  $.ajaxSetup({ cache:true });
  $.ajax({
    url:"/?page=homeTransactions",
    type:"GET",
    success:function(html){
      $("#homeTransactions").html(html);
    }
  });
}

$("#reloadTimer").hide();

if($("#homeBlocks").length > 0){
  $("#reloadTimer").show();
  $("#defaultRefreshTimeout").css('text-decoration','underline');
  refreshInterval = window.setInterval("refreshHome();", refreshTimeout);
}

function updateTimeouts(timer){
  clearInterval(refreshInterval);
  refreshInterval = window.setInterval("refreshHome();", timer);
}

function refreshHome()
{
  homeBlocks();
  homeTransactions();
}

$(".refreshTimeout").click(function(e){
  e.preventDefault();
  var timeout = $(this).attr('data-value')*1000;
  $(".refreshTimeout").css('text-decoration','none');
  $(this).css('text-decoration','underline');
  updateTimeouts(timeout);
});

/* Time/Age switcher */
function timeAgeSwitcher(){
  $(".timeAGE").show();
  $(".TIMEage").hide();
  $(".switchtimeAGE").css('color','black');

  $(".switchTIMEage").click(function(e){
    e.preventDefault();
    $(".TIMEage").show();
    $(".timeAGE").hide();
    $(".switchTIMEage").css('color','black');
    $(".switchtimeAGE").css('color',customBrandcolor);
  });

  $(".switchtimeAGE").click(function(e){
    e.preventDefault();
    $(".TIMEage").hide();
    $(".timeAGE").show();
    $(".switchtimeAGE").css('color','black');
    $(".switchTIMEage").css('color',customBrandcolor);
  });
}

$("#graphlinks").hide();
$("a.active").css('text-decoration','underline');
