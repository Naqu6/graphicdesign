
var slider;
var params;
var FlexPaperFullScreen = false;

$(document).ready(function() {
  fpToolbarLoadButtons();
  addSlider('zoomSlider');

  // enable text search
  $('#text-search-btn').bind('click',function(){
    searchText();
  });

  // enable save button for annotation mode
  $('#annotate-save-btn').bind('click',function(){
    if( $(this).hasClass('disabled') )
      return;
    saveNotes();
  });
});

/**
 * Adds the slider control to the UI
 *
 * @example addSlider( "slider1" );
 *
 * @param String id
 */
function addSlider(id){
	slider = new Slider(id, {
		callback: function(value) {
		},
		animation_callback: function(value) {
			if(value>0){
			  getDocViewer('documentViewer').setZoom(5*value);
			  setZoomText(5*value);
			}
		}
	});
}

/**
 * Handles the event of scale being changed
 *
 * @example onScaleChanged( 100 );
 *
 * @param float val
 */
function onScaleChanged(val){
	slider.setValue(val/5,true);
	slider.slide();
	slider.show();
	setZoomText(val);
}

function setZoomText( val ){
  $("#txtZoomFactor").val(Math.round((val * 100)) + "%");  
}

/**
 * Handles the event of a document is in progress of loading
 *
 */
function onDocumentLoading(){
  $("#progressSpinner").show();

  if(PendingFullScreen)
    setFullScreen(true);

  if(!slider && FlexPaperFullScreen){
	  addSlider('zoomSliderFullScreen');
    fpToolbarLoadButtons();
  }
}

/**
 * Handles the event of fit mode being changed
 *
 * @example onFitModeChanged("Fit Height")
 *
 * @param String mode
 */
function onFitModeChanged(mode){
	$(".tbbutton_fitmode_selected").removeClass("tbbutton_fitmode_selected");

	if(mode == "Fit Height"){
		$("#fit-height-btn").addClass('tbbutton_fitmode_selected');
	}else if(mode == "Fit Width"){
		$("#fit-width-btn").addClass('tbbutton_fitmode_selected');
	}
}

/**
 * Receives messages about view mode being changed
 *
 * @example onViewModeChanged("Tile")
 *
 * @param String mode
 */
function onViewModeChanged(mode){
	$(".tbbutton_viewmode_selected").removeClass("tbbutton_viewmode_selected");

	if(mode=="Tile"){
		$("#tile-view-btn").addClass("tbbutton_viewmode_selected");
	}
	if(mode=="TwoPage"){
		$("#book-view-btn").addClass("tbbutton_viewmode_selected");
	}
	if(mode=="Portrait"){
		$("#portrait-view-btn").addClass("tbbutton_viewmode_selected");
	}
}

/**
 * Receives messages about the document being loaded
 *
 * @example onDocumentLoaded( 20 );
 *
 * @param int totalPages
 */

function onDocumentLoaded(totalPages) {
  $("#lblTotalPages").html(" / " + totalPages);
  $("#progressSpinner").hide();
  $(document).trigger('flexpaper_onDocumentLoaded',[totalPages]);
}

/**
 * Receives error messages when a document is not loading properly
 *
 * @example onDocumentLoadedError( "Network error" );
 *
 * @param String errorMessage
 */
function onDocumentLoadedError(errMessage){

}

/**
 * Recieves progress information about the document being loaded
 *
 * @example onProgress( 100,10000 );
 *
 * @param int loaded
 * @param int total
 */
function onProgress(loadedBytes,totalBytes){
	$("#txt_progress").val('onProgress:' + loadedBytes + '/' + totalBytes + '\n');
}

/**
 * Receives messages about the current page being changed
 *
 * @example onCurrentPageChanged( 10 );
 *
 * @param int pagenum
 */
function onCurrentPageChanged(pagenum){
  $("#txtPageNumber").val(pagenum);
}

/**
 * Receives messages about the current cursor changed
 *
 * @example onCursorModeChanged( "TextSelectorCursor" );
 *
 * @param String cursor
 */
function onCursorModeChanged(cursor){
	$(".tbbutton_cursormode_selected").removeClass("tbbutton_cursormode_selected");

  if(cursor == "TextSelectorCursor"){
    $("#text-select-btn").addClass("tbbutton_cursormode_selected");
  }else{
    $("#hand-select-btn").addClass("tbbutton_cursormode_selected");
  }
}

/**
 * Binds event listeners for the html toolbar controls
 *
 */
function fpToolbarLoadButtons(){

  var toolbar = $('#main-toolbar');

  $('#print-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').printPaper();
  });

  $('#portrait-view-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').switchMode('Portrait');
  });

  $('#book-view-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').switchMode('TwoPage');
  });

  $('#tile-view-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').switchMode('Tile');
  });

  $('#fit-width-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').fitWidth();
  });

  $('#fit-height-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').fitHeight();
  });

  $('#prev-page-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').prevPage();
  });

  $('#next-page-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').nextPage();
  });

  $('#text-select-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').setCurrentCursor('TextSelectorCursor');
  });

  $('#hand-select-btn' , toolbar).bind('click',function(){
    getDocViewer('documentViewer').setCurrentCursor('ArrowCursor');
  });


  $("#txtZoomFactor" , toolbar).bind('keypress', function(e) {
	  var code = (e.keyCode ? e.keyCode : e.which);

	  // enter key pressed
    if( code == 13 ){
      try  {
	      var zf = Number($("#txtZoomFactor").val().replace("%","")) / 100;
        onScaleChanged(zf);
      }catch(error){}
    }
	});

  $("#txtSearchText" , toolbar).bind('keypress', function(e) {
	  var code = (e.keyCode ? e.keyCode : e.which);

	  // enter key pressed
	  if( code == 13 )
      searchText();
	});

  $("#txtPageNumber" , toolbar).bind('keypress', function(e) {
    var code = (e.keyCode ? e.keyCode : e.which);

    // enter key pressed
    if( code == 13 ){
      try{
        getDocViewer('documentViewer').gotoPage($("#txtPageNumber").val());
      }catch(error){}
    }
	});
}

/**
 * Performs a search using the text in the #txtSearchText input box
 *
 */
function searchText() {
  var search_term = $("#txtSearchText").val();
  if( search_term != '' && search_term != null )
    getDocViewer('documentViewer').searchText( search_term );
}

/**
 * Sets the fullscreen mode on the viewer
 *
 * @example setFullScreen(true)
 *
 * @param boolean val
 */
function setFullScreen(val){
  if(val){
    $("#full-screen-btn").addClass("tbbutton_fullscreen_selected");
    FlexPaperFullScreen = true;
  }else{
    FlexPaperFullScreen = false;
  }
}

/**
 * Handles the event of external links getting clicked in the document.
 *
 * @example onExternalLinkClicked("http://www.google.com")
 *
 * @param String link
 */
function onExternalLinkClicked(link){
  //we are forcing all links in our docviewer to open in a new window.
  window.open(link);
}

/**
 * Receives error messages when a document has finished printed
 *
 * @example onDocumentPrinted();
 *
 */
function onDocumentPrinted(){
	$("#txt_eventlog").val('onDocumentPrinted\n' + $("#txt_eventlog").val());
}

/**
 * Handles the event of text getting selected.
 *
 * @example onTextSelected("abc")
 *
 * @param String the text selected
 */
function onTextSelected(text){
	$("#txt_eventlog").val('onTextSelected:' + text + '\n' + $("#txt_eventlog").val());
}

/*
 * For annotation mode, save current marks
 */

function saveNotes(){
  var marks = $FlexPaper('documentViewer').getMarkList();
  if( typeof marks != 'object' )
    marks = [];

  // serialize all mark objects
  marks = $.map ( marks , function( element , index ){
    if( typeof element == 'object' ){
      delete element.mx_internal_uid;

      //Readonly is set to true if user viewing doc is not an admin
      element.readonly = false;
      element = $.param( element );
    }
    return element;
  });
  
  var wrapperObj = $('#annotate-save-wrapper');
  $('img.save-loader', wrapperObj).show();
  $('#annotate-save-btn' , wrapperObj ).addClass('disabled');
  var tokenData = {
    'X-Csrf-Token' : csrf_token,
    'X-Csrf-Key'   : csrf_key,
  }
  $.ajax({
   url: '/docviewer/annotations/save',
   type: 'POST',
   data: { fid: document_fid , 'marks[]': marks },
   traditional: true,
   dataType: 'json',
   headers : tokenData,
   success: function( data , status , xhr ){
     $('img.save-loader', wrapperObj).hide();
     $('#annotate-save-btn' , wrapperObj ).removeClass('disabled');    
   },
   error: function(){
     $('img.save-loader', wrapperObj).hide();
     $('#annotate-save-btn' , wrapperObj ).removeClass('disabled');       
     alert( messages.changes_saved_error );
   }

  });
}