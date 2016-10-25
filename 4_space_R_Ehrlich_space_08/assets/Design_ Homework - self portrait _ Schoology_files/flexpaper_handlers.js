/**
 * Handles the event of annotations getting clicked.
 *
 * @example onMarkClicked(object)
 *
 * @param Object mark that was clicked
 */
function onMarkClicked(mark){
}

/**
 * Handles the event of annotations getting clicked.
 *
 * @example onMarkCreated(object)
 *
 * @param Object mark that was created
 */
function onMarkCreated(mark){
}

/**
 * Handles the event of annotations getting clicked.
 *
 * @example onMarkDeleted(object)
 *
 * @param Object mark that was deleted
 */
function onMarkDeleted(mark){
}

/**
 * Handles the event of annotations getting clicked.
 *
 * @example onMarkChanged(object)
 *
 * @param Object mark that was changed
 */
function onMarkChanged(mark){
}

/**
 * Receives messages about the document being loaded
 *
 * @example onDocumentLoaded( 20 );
 *
 * @param int totalPages
 */

function sDocviewerShowSaveBtn(){
  $('#annotate-save-wrapper').show(); 
}

$(document).bind('flexpaper_onDocumentLoaded',function( e  , totalPages ){
  onScaleChanged(1);
  if(annotations)
    $FlexPaper('documentViewer').addMarks( annotations );
  
  // bind 'show save button' functionality only after loading
  // existing annotations, otherwise the button will show on load
  onMarkDeleted = sDocviewerShowSaveBtn;
  onMarkChanged = sDocviewerShowSaveBtn;
  onMarkCreated = sDocviewerShowSaveBtn;
});
