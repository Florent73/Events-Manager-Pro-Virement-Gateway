// Add WorldPay redirection
$(document).bind('em_booking_gateway_add_virement', function(event, response){
  // called by EM if return JSON contains gateway key, notifications messages are shown by now.
  if(response.result){
    var wpForm = $('<form action="'+response.virement_url+'" method="post" id="em-virement-redirect-form"></form>');
    $.each( response.virement_vars, function(index,value){
      wpForm.append('<input type="hidden" name="'+index+'" value="'+value+'" />');
    });
    wpForm.append('<input id="em-virement-submit" type="submit" style="display:none" />');
    wpForm.appendTo('body').trigger('submit');
  }
});