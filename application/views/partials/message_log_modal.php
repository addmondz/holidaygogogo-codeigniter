<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Shared "View message log" chat modal.
 *
 * Include once on any page that renders a `.js-msg-log` trigger button
 * (data-phone, data-name). Clicking a trigger opens a WhatsApp-style chat
 * popup and loads that contact's stored conversation from `message-log`.
 *
 * Used by: Guest List (views/guests/index.php), Booking listing
 * (views/booking/index.php).
 */
?>
<div class="modal fade" id="messageLogModal" tabindex="-1" role="dialog" aria-labelledby="messageLogModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width:520px;">
		<div class="modal-content">
			<div class="modal-header py-3" style="background:#075E54;">
				<div class="d-flex align-items-center" style="min-width:0;">
					<i class="la la-whatsapp mr-2" style="color:#25D366; font-size:26px;"></i>
					<div style="min-width:0;">
						<div id="messageLogName" class="text-white font-weight-bold text-truncate" style="font-size:15px; line-height:1.2;">Message Log</div>
						<div id="messageLogPhone" class="text-truncate" style="color:#cfe9e2; font-size:12px; line-height:1.2;"></div>
					</div>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" id="messageLogBody" style="background:#ece5dd; min-height:220px; max-height:60vh;">
				<div id="messageLogLoading" class="text-center text-muted py-5" style="font-size:14px;">
					<div class="spinner-border spinner-border-sm mr-2" role="status"></div> Loading messages…
				</div>
				<div id="messageLogEmpty" class="text-center text-muted py-5" style="display:none; font-size:14px;">
					No stored messages for this contact.
				</div>
				<div id="messageLogList"></div>
			</div>
		</div>
	</div>
</div>

<style>
	#messageLogBody .msg-row { display:flex; margin-bottom:10px; }
	#messageLogBody .msg-row.out { justify-content:flex-end; }
	#messageLogBody .msg-bubble {
		max-width:78%; padding:8px 11px; border-radius:9px; font-size:14px; line-height:1.4;
		color:#111; box-shadow:0 1px 1px rgba(0,0,0,.12); word-wrap:break-word; white-space:pre-wrap;
	}
	#messageLogBody .msg-row.in  .msg-bubble { background:#ffffff; border-top-left-radius:2px; }
	#messageLogBody .msg-row.out .msg-bubble { background:#dcf8c6; border-top-right-radius:2px; }
	#messageLogBody .msg-author { font-size:12px; font-weight:600; color:#0b7d66; margin-bottom:2px; }
	#messageLogBody .msg-row.out .msg-author { color:#557a2e; }
	#messageLogBody .msg-time { font-size:11px; color:#667; text-align:right; margin-top:3px; }
</style>

<script>
(function () {
	var loadReq = null;

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function render(messages) {
		var html = '';
		for (var i = 0; i < messages.length; i++) {
			var m = messages[i];
			var side = m.side === 'out' ? 'out' : 'in';
			html += '<div class="msg-row ' + side + '">'
				+ '<div class="msg-bubble">'
				+ '<div class="msg-author">' + esc(m.author) + '</div>'
				+ '<div class="msg-text">' + esc(m.body) + '</div>'
				+ (m.time ? '<div class="msg-time">' + esc(m.time) + '</div>' : '')
				+ '</div></div>';
		}
		return html;
	}

	$(document).on('click', '.js-msg-log', function (e) {
		e.preventDefault();
		var phone = $(this).data('phone');
		var name  = $(this).data('name') || 'Contact';

		$('#messageLogName').text(name);
		$('#messageLogPhone').text(phone ? '+' + String(phone).replace(/^\+/, '') : '');
		$('#messageLogList').empty();
		$('#messageLogEmpty').hide();
		$('#messageLogLoading').show();
		$('#messageLogModal').modal('show');

		if (loadReq && loadReq.abort) { loadReq.abort(); }
		loadReq = $.getJSON('<?php echo base_url('message-log'); ?>', { phone: phone, name: name })
			.done(function (res) {
				$('#messageLogLoading').hide();
				var msgs = (res && res.messages) || [];
				if (!msgs.length) { $('#messageLogEmpty').show(); return; }
				$('#messageLogList').html(render(msgs));
				var body = document.getElementById('messageLogBody');
				body.scrollTop = body.scrollHeight; // jump to latest
			})
			.fail(function (jqXHR, textStatus) {
				if (textStatus === 'abort') { return; }
				$('#messageLogLoading').hide();
				$('#messageLogEmpty').text('Could not load messages. Please try again.').show();
			});
	});
})();
</script>
