const INTERNAL_FIELD_NAMES = new Set( [
	'ace_form_nonce',
	'ace_form_config',
	'ace_form_signature',
	'ace_hp',
] );

function getFormStatusElement( form ) {
	return form.querySelector( '[data-ace-form-status]' );
}

function setFormStatus( form, type, message ) {
	const status = getFormStatusElement( form );
	if ( ! status ) {
		return;
	}

	status.hidden = false;
	status.className = `ace-form-status ace-form-status--${ type }`;
	status.textContent = message;
}

function clearFormStatus( form ) {
	const status = getFormStatusElement( form );
	if ( ! status ) {
		return;
	}

	status.hidden = true;
	status.className = 'ace-form-status';
	status.textContent = '';
}

function getSubmitControls( form ) {
	return [
		...form.querySelectorAll(
			'button[type="submit"], input[type="submit"], .wp-block-button__link'
		),
	];
}

function setSubmittingState( form, isSubmitting ) {
	getSubmitControls( form ).forEach( ( control ) => {
		if ( control.tagName === 'A' ) {
			control.setAttribute(
				'aria-disabled',
				isSubmitting ? 'true' : 'false'
			);
			control.classList.toggle( 'is-disabled', isSubmitting );
			return;
		}

		control.disabled = isSubmitting;
	} );

	form.classList.toggle( 'is-submitting', isSubmitting );
}

function collectFormFields( form ) {
	const formData = new FormData( form );
	const fields = {};

	for ( const [ name, value ] of formData.entries() ) {
		if ( INTERNAL_FIELD_NAMES.has( name ) ) {
			continue;
		}

		if ( Object.prototype.hasOwnProperty.call( fields, name ) ) {
			if ( ! Array.isArray( fields[ name ] ) ) {
				fields[ name ] = [ fields[ name ] ];
			}

			fields[ name ].push( value );
			continue;
		}

		fields[ name ] = value;
	}

	return {
		fields,
		formData,
	};
}

async function submitAceForm( form ) {
	if ( typeof form.checkValidity === 'function' && ! form.checkValidity() ) {
		if ( typeof form.reportValidity === 'function' ) {
			form.reportValidity();
		}

		return;
	}

	clearFormStatus( form );
	setSubmittingState( form, true );

	const { fields, formData } = collectFormFields( form );

	try {
		const response = await fetch( window.aceContactForm.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				ace_form_nonce: formData.get( 'ace_form_nonce' ),
				ace_form_config: formData.get( 'ace_form_config' ),
				ace_form_signature: formData.get( 'ace_form_signature' ),
				ace_hp: formData.get( 'ace_hp' ) || '',
				page_url: window.location.href,
				fields,
			} ),
		} );

		const data = await response.json();

		if ( ! response.ok || ! data.success ) {
			throw new Error(
				data.message ||
					form.dataset.errorMessage ||
					'There was an error sending your message.'
			);
		}

		form.reset();
		setFormStatus(
			form,
			'success',
			data.message ||
				form.dataset.successMessage ||
				'Thank you for your message!'
		);
	} catch ( error ) {
		setFormStatus(
			form,
			'error',
			error.message ||
				form.dataset.errorMessage ||
				'There was an error sending your message.'
		);
	} finally {
		setSubmittingState( form, false );
	}
}

document.addEventListener( 'click', ( event ) => {
	const buttonLink = event.target.closest( '.wp-block-button__link' );
	if ( ! buttonLink ) {
		return;
	}

	const form = buttonLink.closest( 'form[data-ace-form="true"]' );
	if ( ! form ) {
		return;
	}

	event.preventDefault();

	if ( typeof form.requestSubmit === 'function' ) {
		form.requestSubmit();
		return;
	}

	if ( typeof form.checkValidity === 'function' && ! form.checkValidity() ) {
		if ( typeof form.reportValidity === 'function' ) {
			form.reportValidity();
		}

		return;
	}

	form.dispatchEvent(
		new Event( 'submit', { bubbles: true, cancelable: true } )
	);
} );

document.addEventListener( 'DOMContentLoaded', () => {
	document
		.querySelectorAll( 'form[data-ace-form="true"]' )
		.forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				if (
					typeof form.checkValidity === 'function' &&
					! form.checkValidity()
				) {
					if ( typeof form.reportValidity === 'function' ) {
						form.reportValidity();
					}

					event.preventDefault();
					return;
				}

				event.preventDefault();
				submitAceForm( form );
			} );
		} );
} );
