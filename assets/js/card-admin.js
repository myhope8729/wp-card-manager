jQuery(document).ready(function($){
	$('.delete_category').click(function(e){
		var _this = $(this);
		e.preventDefault();
		var data = {
			'action' : 'delete_msg_category',
			'category_id' : $(this).data('id')
		}
		$.ajax({
			url: $(this).attr('href'),
			data: data,
			type: 'POST',
			success: function(){
				_this.closest('tr').remove();
			}
		})
	});

	$('.delete_message').click(function(e){
		var _this = $(this);
		e.preventDefault();
		var data = {
			'action' : 'delete_card_message',
			'message_id' : $(this).data('id')
		}
		$.ajax({
			url: $(this).attr('href'),
			data: data,
			type: 'POST',
			success: function(){
				_this.closest('tr').remove();
			}
		})
	});
});