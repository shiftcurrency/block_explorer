var randomlyPlace = function(el){
  el.css({
    "position": "absolute",
    "top" : Math.floor(Math.random()*document.body.clientHeight)+"px",
    "left" : Math.floor(Math.random()*document.body.clientWidth)+"px"
  });
};

randomlyPlace($("#easteregg"));