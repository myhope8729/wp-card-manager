jQuery(document).ready(function($){
	if ( $('.card-modal').length > 0 ){
		WebFont.load({
			google: {
				families: ["Abril Fatface", "Acme", "Alfa Slab One", "Amatic SC", "Amiri", "Bangers", "Bebas Neue", "Bree Serif", "Cookie", "Dancing Script", "Fredoka One", "Great Vibes", "Lateef", "Lobster", "Luckiest Guy", "Merriweather", "Pacifico", "Passion One", "Playfair Display", "Rancho", "Roboto", "Roboto Slab", "Rochester", "Sacramento", "Sen"]
			}
		});
	}
	
	$('.card-modal .btn-fonts button').click(function(){
		$(this).closest('.card-modal').find('textarea').css('font-family', $(this).html());
		$(this).closest('.btn-group').find('.dropdown-toggle span').html($(this).html());
		var editor = $(this).closest('.card-modal').find('textarea');
		editor.trigger('keyup');
	});
	$('.card-send-dlg').on('show.bs.modal', function(e) {
		$('body').addClass('card-dlg');
		$(this).find('.msg-category button').eq(0).trigger('click');
	}).on('hide.bs.modal', function(e){
		$('body').removeClass('card-dlg');
	});
	$('.card-modal .btn-font-size button').click(function(){
		$(this).closest('.card-modal').find('textarea').css('font-size', $(this).html());
		$(this).closest('.btn-group').find('.dropdown-toggle span').html($(this).html());
		
		var editor = $(this).closest('.card-modal').find('textarea');
		editor.trigger('keyup');
	});

	$('.card-modal .btn-text-align button').click(function(){
		$(this).closest('.card-modal').find('textarea').css('text-align', $(this).data('align'));
		$(this).closest('.btn-group').find('.dropdown-toggle span').html($(this).html());
	});

	$('.card-modal .icon-more').click(function(){
		$(this).closest('.card-modal').find('.more_colors').toggle();
		$(this).closest('.colors').toggleClass('opened');
	});

	$('.card-modal .text-color').click(function(){
		$(this).closest('.card-modal').find('textarea').css('color', $(this).find('span').css('background-color'));
	});

	$('.card-modal .msg-category button').click(function(){
		var cat_id = $(this).data('cat');
		$(this).closest('.card-modal').find('.card_messages .message').removeClass('show');
		$(this).closest('.card-modal').find('.card_messages .cat-' + cat_id).addClass('show');
		$(this).closest('.message_category').find('.dropdown-toggle').html($(this).html());
	});

	$('.card-modal .card_messages .message').click(function(){
		$(this).closest('.card-modal').find('textarea').val($(this).html()).trigger('keyup');
		$(this).closest('.card-modal').find('.suggested_messages').toggleClass('open');
	});

	$('.card-modal .row_message').click(function(){
		$(this).closest('.card-modal').find('.suggested_messages').toggleClass('open');

	});

	$('.card-modal .close_message').click(function(){
		$(this).closest('.card-modal').find('.suggested_messages').toggleClass('open');
	});	

	$('.card-modal .btn-whatsapp').click(function(){
		var card_img = $(this).closest('.card-modal').find('.card_img').val();
		var font_text = $(this).closest('.card-modal').find('textarea').css('font-family').replace(/"/g,"");
		var font_size_text = $(this).closest('.card-modal').find('textarea').css('font-size').replace(/"/g,"");
		var align_text = $(this).closest('.card-modal').find('textarea').css('text-align').replace(/"/g,"");
		var font_color = $(this).closest('.card-modal').find('textarea').css('color').replace(/"/g,"");

		var content = $("<div/>").html($(this).closest('.card-modal').find('textarea').val()).text();
		content = '<div style="font-family:' + font_text + ';font-size:' + font_size_text + ';text-align:' + align_text + ';color:' + font_color + ';">' + content + '</div>';
		$.ajax({
			url: ajaxObj.ajaxurl,
			dataType: 'json',
			type: 'post',
			data: { 'action':'save_card_content', 'card_img':card_img, 'content':content},
			success:function(data){
				if ( data.result != "error"){
					window.open( "https://web.whatsapp.com/send?text=" + data.url );
				}
			}
		});
	});

	$('.card-modal .btn-facebook').click(function(){
		var card_img = $(this).closest('.card-modal').find('.card_img').val();
		var font_text = $(this).closest('.card-modal').find('textarea').css('font-family').replace(/"/g,"");
		var font_size_text = $(this).closest('.card-modal').find('textarea').css('font-size').replace(/"/g,"");
		var align_text = $(this).closest('.card-modal').find('textarea').css('text-align').replace(/"/g,"");
		var font_color = $(this).closest('.card-modal').find('textarea').css('color').replace(/"/g,"");

		var content = $("<div/>").html($(this).closest('.card-modal').find('textarea').val()).text();
		content = '<div style="font-family:' + font_text + ';font-size:' + font_size_text + ';text-align:' + align_text + ';color:' + font_color + ';">' + content + '</div>';
		$.ajax({
			url: ajaxObj.ajaxurl,
			dataType: 'json',
			type: 'post',
			data: { 'action':'save_card_content', 'card_img':card_img, 'content':content },
			success:function(data){
				if ( data.result != "error"){
					window.open( "http://www.facebook.com/sharer.php?u=" + data.url );
				}
			}
		});
	});

	$('.card-modal .btn-copy-link').click(function(){
		var card_img = $(this).closest('.card-modal').find('.card_img').val();
		var font_text = $(this).closest('.card-modal').find('textarea').css('font-family').replace(/"/g,"");
		var font_size_text = $(this).closest('.card-modal').find('textarea').css('font-size').replace(/"/g,"");
		var align_text = $(this).closest('.card-modal').find('textarea').css('text-align').replace(/"/g,"");
		var font_color = $(this).closest('.card-modal').find('textarea').css('color').replace(/"/g,"");

		var content = $("<div/>").html($(this).closest('.card-modal').find('textarea').val()).text();
		content = '<div style="font-family:' + font_text + ';font-size:' + font_size_text + ';text-align:' + align_text + ';color:' + font_color + ';">' + content + '</div>';
		$.ajax({
			url: ajaxObj.ajaxurl,
			dataType: 'json',
			type: 'post',
			data: { 'action':'save_card_content', 'card_img':card_img, 'content':content },
			success:function(data){
				var tempElem = document.createElement("input");
				document.body.appendChild(tempElem);
				tempElem.setAttribute("id", "temp_elem");
				document.getElementById("temp_elem").value=data.url;
				tempElem.select();
				tempElem.setSelectionRange(0, 99999);
				document.execCommand("copy");
				document.body.removeChild(tempElem);
				alert('Link has been copied');
			}
		});
	});

	if ( $('.card-wrapper').length > 0 ){
		if ( $('.card-back').length > 0 ){
			WebFont.load({
				google: {
					families: ["Abril Fatface", "Acme", "Alfa Slab One", "Amatic SC", "Amiri", "Bangers", "Bebas Neue", "Bree Serif", "Cookie", "Dancing Script", "Fredoka One", "Great Vibes", "Lateef", "Lobster", "Luckiest Guy", "Merriweather", "Pacifico", "Passion One", "Playfair Display", "Rancho", "Roboto", "Roboto Slab", "Rochester", "Sacramento", "Sen"]
				}
			});

			var actual_width = $('.card-back').width();
			var scale_amount = actual_width / 320;
			var flex_val = 100 / scale_amount;
			$('.card-back > div').css( {'transform':'scale(' + scale_amount + ')', 'flex':'0 0 ' + flex_val + '%' } );

		}
		$(".layout").addClass("layout-is-ready");
		setTimeout(function () {
			$(".envelope").removeClass("first-hidden");
			$(".envelope").addClass("envelope-is-shown");
		}, 2000);
		setTimeout(function () {
			$(".layout").addClass("overflow-visible");
			$(".envelope").removeClass("envelope-is-shown");
			$(".envelope").addClass("envelope-is-opening")
		}, 4000);
		setTimeout(function () {
			$(".envelope").removeClass("envelope-is-opening");
			$(".envelope").addClass("envelope-is-opened");
		}, 5100);
		setTimeout( function() {
			$(".card-wrapper .flip").fadeIn('slow');
		}, 6200);
	}

	$('.card-wrapper .flip').click(function(){
		$(".envelope .card").toggleClass('flip');
	});

	$('.editor_wrapper .editor').keyup(function(e){
		var editor = $(this);
		editor.height( editor.css('line-height') );
		editor.height( editor[0].scrollHeight );
		if ( editor[0].scrollHeight > $(this).parent().height() ){
			var lineHeight = parseFloat( editor.css('line-height') );
			var lineCount = Math.round( editor[0].scrollHeight / lineHeight );
			var newLineHeight = editor.height() / lineCount;
			var newFontSize = Math.floor( newLineHeight / 1.2 );

			editor.css( 'font-size', newFontSize );
			editor.closest('.card-modal').find('.font-size-display span').html( newFontSize + 'px' );	
		}
	});

	$('.editor_wrapper .editor').focus(function(){
		$(this).attr('placeholder', '');
	});
	$('.editor_wrapper .editor').blur(function(){
		$(this).attr('placeholder', 'Add Text');
	});
});