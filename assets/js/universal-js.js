function Delete_Record(background, title, url, key, value, status, href)
{
	const swalWithBootstrapButtons = Swal.mixin({
		customClass: {
			confirmButton: 'btn btn-light-success m-2',
			cancelButton: 'btn btn-danger m-2'
		},
		buttonsStyling: true
	});
	swalWithBootstrapButtons.fire({
		width: 550,
		background: `url(${background})`,
		icon: 'warning',
		title: `Delete ${title} ?`,
		confirmButtonText: 'Confirm',
		cancelButtonText: 'Cancel',
		showCancelButton: true
	}).then((action) => {
		if(action.isConfirmed) {
			$.ajax({
				url: url,
				type: 'get',
				data: {
					[key]: value,
					status: status
				},
				timeout: 2000,
				success: function() {
					Display_Message(background, `${title} Successfully Deleted`, href);
				},
				error: function() {
					Display_Message(background, `${title} Could Not Be Deleted`, null);
				}
			});
		}
	});
}

function Display_Message(background, title, url, showConfirmButton = false)
{
	Swal.fire({
		width: 550,
		background: `url(${background})`,
		icon: title.includes('Successfully') || title.includes('No Changes') ? 'success' : 'error',
		title: title,
		showConfirmButton: showConfirmButton,
		...(confirm ? {} : { timer: 2200 })
	}).then(() => {
		if(title.includes('Successfully') || title.includes('No Changes') || title.includes('Page Will Refresh')) {
			window.location.href = url;
		}
	});
}

function Reset(url)
{
	window.location.href = url;
}