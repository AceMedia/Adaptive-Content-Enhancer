import { __, sprintf } from '@wordpress/i18n';

document.addEventListener( 'DOMContentLoaded', () => {
	for ( const form of document.querySelectorAll( 'form[data-ace-form]' ) ) {
		for ( const select of form.querySelectorAll( 'select[data-ace-association]' ) ) {
			const search = select.parentElement.querySelector( '[data-ace-association-search]' );
			if ( ! search ) continue;
			let timer;
			let controller;
			search.addEventListener( 'input', () => {
				clearTimeout( timer );
				timer = setTimeout( async () => {
					controller?.abort();
					controller = new AbortController();
					const config = JSON.parse( select.dataset.aceAssociation );
					const selected = [ ...select.selectedOptions ].filter( ( option ) => option.value ).map( ( option ) => ( { value: option.value, label: option.textContent } ) );
					const params = new URLSearchParams( { type: config.type, search: search.value } );
					for ( const slug of config.postTypes || [] ) params.append( 'postTypes[]', slug );
					for ( const slug of config.taxonomies || [] ) params.append( 'taxonomies[]', slug );
					for ( const option of selected ) params.append( 'selected[]', option.value );
					select.setAttribute( 'aria-busy', 'true' );
					try {
						const response = await fetch( `${ select.dataset.aceAssociationUrl }?${ params }`, { credentials: 'same-origin', signal: controller.signal } );
						if ( ! response.ok ) return;
						const options = await response.json();
						const selectedValues = new Set( selected.map( ( option ) => option.value ) );
						select.replaceChildren();
						if ( ! select.multiple ) select.add( new Option( __( 'Choose…', 'adaptive-content-enhancer' ), '' ) );
						for ( const option of [ ...selected, ...options ] ) {
							if ( [ ...select.options ].some( ( item ) => item.value === option.value ) ) continue;
							const item = new Option( option.label, option.value );
							item.selected = selectedValues.has( option.value );
							select.add( item );
						}
					} catch ( error ) {
						if ( error.name !== 'AbortError' ) select.dataset.aceSearchError = 'true';
					} finally {
						select.removeAttribute( 'aria-busy' );
					}
				}, 250 );
			} );
		}
		const conditionalFields = [ ...form.querySelectorAll( '[data-ace-condition]' ) ];
		const updateConditions = () => {
			for ( let pass = 0; pass < conditionalFields.length; pass++ ) {
				for ( const wrapper of conditionalFields ) {
					let condition;
					try { condition = JSON.parse( wrapper.dataset.aceCondition ); } catch { continue; }
					const data = new FormData( form );
					const actual = [ ...data.getAll( condition.field ), ...data.getAll( `${ condition.field }[]` ) ].map( String );
					if ( ! actual.length ) actual.push( '' );
					const equal = actual.includes( String( condition.value || '' ) );
					const visible = condition.operator === '!=' ? ! equal : equal;
					wrapper.hidden = ! visible;
					for ( const control of wrapper.querySelectorAll( 'input, select, textarea' ) ) {
						if ( ! control.dataset.aceOriginalDisabled ) control.dataset.aceOriginalDisabled = control.disabled ? 'yes' : 'no';
						control.disabled = ! visible || control.dataset.aceOriginalDisabled === 'yes';
					}
				}
			}
		};
		form.addEventListener( 'input', updateConditions );
		form.addEventListener( 'change', updateConditions );
		updateConditions();
		const steps = [ ...form.querySelectorAll( '[data-ace-step]' ) ];
		if ( steps.length > 1 ) {
			const submitWrapper = form.querySelector( '[type="submit"]' )?.closest( '.wp-block-adaptive-content-enhancer-submit' );
			const progress = document.createElement( 'p' );
			progress.className = 'ace-form-step-progress';
			progress.setAttribute( 'role', 'status' );
			steps[ 0 ].before( progress );
			let activeStep = 0;
			const showStep = ( index ) => {
				activeStep = index;
				steps.forEach( ( step, stepIndex ) => { step.hidden = stepIndex !== activeStep; } );
				if ( submitWrapper && ! submitWrapper.closest( '[data-ace-step]' ) ) submitWrapper.hidden = activeStep !== steps.length - 1;
				progress.textContent = sprintf( __( 'Step %1$d of %2$d', 'adaptive-content-enhancer' ), activeStep + 1, steps.length );
			};
			steps.forEach( ( step, index ) => {
				const nav = document.createElement( 'div' );
				nav.className = 'ace-form-step-nav';
				if ( index > 0 ) {
					const back = document.createElement( 'button' );
					back.type = 'button';
					back.textContent = __( 'Back', 'adaptive-content-enhancer' );
					back.addEventListener( 'click', () => { showStep( index - 1 ); steps[ index - 1 ].focus(); } );
					nav.append( back );
				}
				if ( index < steps.length - 1 ) {
					const next = document.createElement( 'button' );
					next.type = 'button';
					next.textContent = __( 'Continue', 'adaptive-content-enhancer' );
					next.addEventListener( 'click', () => {
						for ( const control of step.querySelectorAll( 'input, select, textarea' ) ) {
							if ( ! control.checkValidity() ) { control.reportValidity(); return; }
						}
						showStep( index + 1 );
						steps[ index + 1 ].focus();
					} );
					nav.append( next );
				}
				step.tabIndex = -1;
				step.append( nav );
			} );
			showStep( 0 );
		}
		const started = form.querySelector( '[name="ace_started_at"]' );
		if ( started ) started.value = String( Math.floor( Date.now() / 1000 ) );
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			if ( ! form.reportValidity() ) return;
			const status = form.querySelector( '.ace-form-status' );
			const button = form.querySelector( '[type="submit"]' );
			for ( const error of form.querySelectorAll( '.ace-field-error' ) ) error.remove();
			for ( const control of form.querySelectorAll( '[aria-invalid="true"]' ) ) {
				control.removeAttribute( 'aria-invalid' );
				control.removeAttribute( 'aria-describedby' );
			}
			let firstInvalid = null;
			const formData = new FormData( form );
			const fields = {};
			const files = [];
			for ( const [ rawKey, value ] of formData ) {
				const key = rawKey.endsWith( '[]' ) ? rawKey.slice( 0, -2 ) : rawKey;
				if ( [ 'action', 'form_id', 'ace_started_at', 'ace_hp', 'cf-turnstile-response' ].includes( key ) ) continue;
				if ( value instanceof File ) {
					if ( value.name ) files.push( [ key, value ] );
					continue;
				}
				if ( Object.prototype.hasOwnProperty.call( fields, key ) ) {
					fields[ key ] = [ fields[ key ], value ].flat();
				} else fields[ key ] = value;
			}
			if ( button ) button.disabled = true;
			status.hidden = true;
			try {
				const payload = { fields, ace_hp: formData.get( 'ace_hp' ), ace_started_at: formData.get( 'ace_started_at' ), turnstile_token: formData.get( 'cf-turnstile-response' ) || '', page_url: location.href };
				const multipart = files.length > 0 ? new FormData() : null;
				if ( multipart ) {
					for ( const [ key, value ] of Object.entries( payload ) ) multipart.append( key, 'fields' === key ? JSON.stringify( value ) : value );
					for ( const [ key, file ] of files ) multipart.append( `ace_file_${ key }`, file );
				}
				const response = await fetch( `${ form.dataset.restRoot }ace-forms/v1/forms/${ encodeURIComponent( form.dataset.aceForm ) }/entries`, {
					method: 'POST',
					credentials: 'same-origin',
					...( multipart ? {} : { headers: { 'Content-Type': 'application/json' } } ),
					body: multipart || JSON.stringify( payload ),
				} );
				const result = await response.json();
				if ( ! response.ok || ! result.success ) {
					for ( const [ key, message ] of Object.entries( result.data?.fields || {} ) ) {
						const item = form.elements.namedItem( key ) || form.elements.namedItem( `${ key }[]` );
						const control = item?.length ? item[ 0 ] : item;
						if ( ! control ) continue;
						const error = document.createElement( 'p' );
						error.className = 'ace-field-error';
						error.id = `${ control.id }-error`;
						error.textContent = message;
						control.setAttribute( 'aria-invalid', 'true' );
						control.setAttribute( 'aria-describedby', error.id );
						control.closest( '.ace-form-field' )?.append( error );
						firstInvalid ||= control;
					}
					throw new Error( result.message || 'Your enquiry could not be sent.' );
				}
				form.reset();
				updateConditions();
				window.turnstile?.reset();
				if ( started ) started.value = String( Math.floor( Date.now() / 1000 ) );
				status.className = 'ace-form-status ace-form-status--success';
				status.textContent = result.message || form.dataset.successMessage;
				const detail = { formId: form.dataset.aceForm, formName: result.formName || '', entryId: result.entryId, conversion: true };
				form.dispatchEvent( new CustomEvent( 'ace-forms:submitted', { bubbles: true, detail } ) );
				form.dispatchEvent( new CustomEvent( 'ace:form-submitted', { bubbles: true, detail } ) );
				window.dataLayer?.push( { event: 'ace_form_submit', form_id: detail.formId, form_name: detail.formName, entry_id: detail.entryId } );
			} catch ( error ) {
				window.turnstile?.reset();
				status.className = 'ace-form-status ace-form-status--error';
				status.textContent = error.message;
			} finally {
				status.hidden = false;
				( firstInvalid || status ).focus();
				if ( button ) button.disabled = false;
			}
		} );
	}
} );
