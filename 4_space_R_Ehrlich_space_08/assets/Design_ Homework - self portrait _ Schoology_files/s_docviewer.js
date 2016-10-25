
$(document).ready(function(){
  $(window).bind("resize", function(){
    var maxHeight = $(window).height(),
        flashObject = $('#flash-pdf object'),
        flashObjectOffset = flashObject.offset().top;
    flashObject.height(maxHeight - flashObjectOffset);
  });
});