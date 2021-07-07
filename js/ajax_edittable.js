jQuery(document).ready(function(){
								
  jQuery(".info_link").click(function(){
	var ID=jQuery(this).attr('id');
	var prefix="intercept_";
	var sentId= ID.substring(prefix.length, ID.length)
	var dataString = { action: 'delete', id: ''+ sentId+''};    
	jQuery.post(ajaxurl, dataString , function(response) {
		if(response>0){
			document.location.href = document.location.href;
		}

	});

  });
	jQuery(".edit_tr").click(function(){
		var ID=jQuery(this).attr('id');
		var firstBefore=jQuery("#first_input_"+ID).val();
		jQuery("#first_"+ID).hide();
		jQuery("#first_input_"+ID).show();
		jQuery("#hideaway_"+ID).show();
	}).change(function(){
		var ID=jQuery(this).attr('id');
		var first=jQuery("#first_input_"+ID).val();
		var dataString = { action: 'foo', id: ''+ ID+'', 'firstname': ''+first+''};
		if(first.length>0){
			jQuery.post(ajaxurl, dataString , function(response) {
				if(response>0){
					jQuery("#first_"+ID).html(first);
				}else {
					alert("Non puoi aggiornare questo nome perchè non sei l\' utente che l\'ha inserito");
					window.location.reload();
				}
			});
		}
	});

	// Edit input box click action
	jQuery(".editbox").mouseup(function(){
		return false
	});

	// Outside click action
	jQuery(document).mouseup(function(){
		jQuery(".editbox").hide();
		jQuery(".text").show();
	});
});