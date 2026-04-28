( function () {
	'use strict';

	const config = window.devhubCheckoutData || {};
	const fields = config.fields || {};
	const locations = Array.isArray( config.pickupLocations ) ? config.pickupLocations : [];
	const messages = config.messages || {};

	const DELIVERY_FIELD = fields.deliveryMethod || 'devicehub/delivery_method';
	const PICKUP_FIELD = fields.pickupStore || 'devicehub/pickup_store';
	const BILLING_EMAIL_FIELD = fields.billingEmail || 'devicehub/billing_email';
	const CART_STORE_KEY = window.wc?.wcBlocksData?.CART_STORE_KEY || 'wc/store/cart';
	const CHECKOUT_STORE_KEY = window.wc?.wcBlocksData?.CHECKOUT_STORE_KEY || 'wc/store/checkout';
	const VALIDATION_STORE_KEY = window.wc?.wcBlocksData?.VALIDATION_STORE_KEY || 'wc/store/validation';
	const DELIVERY_ERROR_KEY = 'devhub-pickup-store';
	const PLACE_ORDER_SELECTOR = '.wc-block-components-checkout-place-order-button';
	const ORDER_SUMMARY_SELECTOR = '.wc-block-checkout__sidebar .wp-block-woocommerce-checkout-order-summary-block';
	const ORDER_SUMMARY_ITEM_SELECTOR = '.wc-block-components-order-summary-item';
	const CHECKOUT_SIDEBAR_SELECTOR = '.wc-block-checkout__sidebar';
	const ORDER_NOTE_PLACEHOLDER_SELECTOR = '.devhub-checkout-order-note-placeholder';
	const PAYMENT_STEP_SELECTOR = '.wp-block-woocommerce-checkout-payment-block';
	const PAYMENT_PLACEHOLDER_SELECTOR = '.devhub-checkout-payment-placeholder';
	const SIDEBAR_RELOCATION_CLASS = 'devhub-checkout--sidebar-relocation';
	const EMPTY_CHECKOUT_BUTTON_SELECTOR = '.wc-block-checkout-empty .wp-block-button__link';
	const COUPON_BUTTON_SELECTOR = '.wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__button';
	const COUPON_INPUT_SELECTOR = '.wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__input input';
	const COUPON_INPUT_LABEL_SELECTOR = '.wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__input label';
	const CONTACT_EMAIL_INPUT_SELECTOR = '.wc-block-checkout__contact-fields .wc-block-components-text-input input[type="email"]';
	const CONTACT_EMAIL_LABEL_SELECTOR = '.wc-block-checkout__contact-fields .wc-block-components-text-input label';
	const BILLING_STEP_SELECTOR = '.wc-block-checkout__shipping-fields, .wc-block-checkout__billing-address, .wp-block-woocommerce-checkout-billing-address-block';
	const BILLING_ADDRESS_FORM_SELECTOR = '.wc-block-components-address-form';
	const BILLING_ADDRESS_CARD_SELECTOR = '.wc-block-components-address-card';
	const BILLING_EMAIL_FIELD_CLASS = 'devhub-checkout-billing-email-field';
	const ADDRESS_LINE_2_TOGGLE_SELECTOR = '.wc-block-components-address-form__address_2-toggle';
	const NATIVE_DELIVERY_STEP_SELECTOR = '.wc-block-checkout__shipping-method, #shipping-method';
	const NATIVE_DELIVERY_OPTION_SELECTOR = `${ NATIVE_DELIVERY_STEP_SELECTOR } .wc-block-components-radio-control__option`;
	const NATIVE_DELIVERY_CARD_SELECTOR = `${ NATIVE_DELIVERY_STEP_SELECTOR } .wc-block-checkout__shipping-method-option`;
	const NATIVE_PICKUP_STEP_SELECTOR = '.wc-block-checkout__pickup-options';
	const NATIVE_PICKUP_OPTION_SELECTOR = '.wc-block-checkout__pickup-options .wc-block-components-radio-control__option';
	const NATIVE_PICKUP_INPUT_SELECTOR = '.wc-block-checkout__pickup-options input[type="radio"]';
	const DESKTOP_SIDEBAR_MEDIA = '(min-width: 782px)';

	const state = {};

	let unsubscribe = null;
	let lastSignature = '';
	let hasBoundViewportListener = false;

	function getCheckoutStore() {
		return window.wp?.data?.select?.( CHECKOUT_STORE_KEY ) || null;
	}

	function getCartStore() {
		return window.wp?.data?.select?.( CART_STORE_KEY ) || null;
	}

	function getCartDispatch() {
		return window.wp?.data?.dispatch?.( CART_STORE_KEY ) || null;
	}

	function getCheckoutDispatch() {
		return window.wp?.data?.dispatch?.( CHECKOUT_STORE_KEY ) || null;
	}

	function getCartData() {
		return getCartStore()?.getCartData?.() || {};
	}

	function getValidationDispatch() {
		return window.wp?.data?.dispatch?.( VALIDATION_STORE_KEY ) || null;
	}

	function getAdditionalFields() {
		return getCheckoutStore()?.getAdditionalFields?.() || {};
	}

	function patchAdditionalFields( patch ) {
		const dispatch = getCheckoutDispatch();
		if ( ! dispatch?.setAdditionalFields ) {
			return;
		}

		dispatch.setAdditionalFields( {
			...getAdditionalFields(),
			...patch,
		} );
	}

	function setPrefersCollection( method ) {
		const dispatch = getCheckoutDispatch();
		if ( dispatch?.setPrefersCollection ) {
			dispatch.setPrefersCollection( method === 'pickup' );
		}
	}

	function normalizeText( value ) {
		return String( value ?? '' )
			.replace( /\s+/g, ' ' )
			.trim()
			.toLowerCase();
	}

	function isValidMethod( method ) {
		return method === 'home_delivery' || method === 'pickup';
	}

	function getLocationMap() {
		return locations.reduce( ( carry, location ) => {
			carry[ location.value ] = location;
			return carry;
		}, {} );
	}

	function getNativeDeliveryOptions() {
		const cardOptions = Array.from( document.querySelectorAll( NATIVE_DELIVERY_CARD_SELECTOR ) );

		if ( cardOptions.length ) {
			return cardOptions.map( ( option ) => ( {
				option,
				input: null,
				rateId: getNativeOptionRateId( option ),
				text: normalizeText( option.textContent ),
				selected:
					option.classList.contains( 'wc-block-checkout__shipping-method-option--selected' ) ||
					option.getAttribute( 'aria-checked' ) === 'true' ||
					option.getAttribute( 'aria-pressed' ) === 'true',
			} ) );
		}

		return Array.from( document.querySelectorAll( NATIVE_DELIVERY_OPTION_SELECTOR ) ).map( ( option ) => ( {
			option,
			input: option.querySelector( 'input[type="radio"]' ),
			rateId: getNativeOptionRateId( option ),
			text: normalizeText( option.textContent ),
			selected: !! option.querySelector( 'input[type="radio"]:checked' ),
		} ) );
	}

	function getNativeOptionRateId( option ) {
		if ( ! option ) {
			return '';
		}

		const input =
			option.matches?.( 'input[type="radio"]' )
				? option
				: option.querySelector?.( 'input[type="radio"]' );
		const candidateValues = [
			input?.value,
			option.dataset?.rateId,
			option.dataset?.shippingRate,
			option.dataset?.shippingMethodId,
			option.getAttribute?.( 'data-rate-id' ),
			option.getAttribute?.( 'data-shipping-rate' ),
			option.getAttribute?.( 'data-shipping-method-id' ),
			option.getAttribute?.( 'data-value' ),
			option.getAttribute?.( 'value' ),
			option.getAttribute?.( 'id' ),
		];

		for ( const candidate of candidateValues ) {
			const normalized = normalizeText( candidate );

			if ( normalized ) {
				return normalized;
			}
		}

		return '';
	}

	function getMethodFromRateId( rateId ) {
		const normalizedRateId = normalizeText( rateId );

		if ( ! normalizedRateId ) {
			return '';
		}

		const methodId = normalizedRateId.split( ':' )[ 0 ];

		if ( methodId === 'pickup_location' || methodId === 'local_pickup' ) {
			return 'pickup';
		}

		return 'home_delivery';
	}

	function getMethodFromNativeOption( option ) {
		const methodFromRateId = getMethodFromRateId( option?.rateId );

		if ( methodFromRateId ) {
			return methodFromRateId;
		}

		const text = normalizeText( option?.text );

		if ( ! text ) {
			return '';
		}

		if ( text.includes( 'pickup' ) || text.includes( 'collect' ) ) {
			return 'pickup';
		}

		if ( text.includes( 'ship' ) || text.includes( 'delivery' ) || text.includes( 'home' ) ) {
			return 'home_delivery';
		}

		return '';
	}

	function getNativePickupOptions() {
		return Array.from( document.querySelectorAll( NATIVE_PICKUP_OPTION_SELECTOR ) ).map( ( option ) => ( {
			option,
			input: option.querySelector( 'input[type="radio"]' ),
			text: normalizeText( option.textContent ),
		} ) );
	}

	function findLocationByNativeText( text ) {
		const normalizedText = normalizeText( text );

		return locations.find( ( location ) => {
			const name = normalizeText( location.name );
			const address = normalizeText( location.address );
			return (
				( name && normalizedText.includes( name ) ) ||
				( address && normalizedText.includes( address ) )
			);
		} ) || null;
	}

	function syncPickupStoreFromNativeSelection() {
		const additionalFields = getAdditionalFields();
		const method = additionalFields[ DELIVERY_FIELD ];
		const pickupStore = additionalFields[ PICKUP_FIELD ] || '';

		if ( method !== 'pickup' || pickupStore ) {
			return false;
		}

		const selectedOption = getNativePickupOptions().find( ( option ) => option.input?.checked );
		const matchedLocation = selectedOption ? findLocationByNativeText( selectedOption.text ) : null;

		if ( ! matchedLocation ) {
			return false;
		}

		patchAdditionalFields( {
			[ PICKUP_FIELD ]: matchedLocation.value,
		} );

		return true;
	}

	function syncNativePickupSelection( pickupStore ) {
		const selectedLocation = getLocationMap()[ pickupStore ] || null;

		if ( ! selectedLocation ) {
			return;
		}

		const targetOption = getNativePickupOptions().find( ( option ) => {
			const matchedLocation = findLocationByNativeText( option.text );
			return matchedLocation?.value === selectedLocation.value;
		} );

		if ( ! targetOption?.input || targetOption.input.checked ) {
			return;
		}

		targetOption.input.checked = true;
		targetOption.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function syncNativeDeliverySelection( method ) {
		const targetOption = getNativeDeliveryOptions().find(
			( option ) => getMethodFromNativeOption( option ) === method
		);

		if ( ! targetOption?.input || targetOption.input.checked ) {
			return;
		}

		targetOption.input.checked = true;
		targetOption.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function getSelectedNativeDeliveryMethod() {
		const selectedOption = getNativeDeliveryOptions().find(
			( option ) => option.input?.checked || option.selected
		);

		return selectedOption ? getMethodFromNativeOption( selectedOption ) : '';
	}

	function getOrderSummaryDeliveryLabel( method, pickupStore ) {
		if ( method === 'pickup' ) {
			const selectedLocation = getLocationMap()[ pickupStore ] || null;

			if ( selectedLocation?.name ) {
				return `Pickup (${ selectedLocation.name })`;
			}

			return 'Store Pickup';
		}

		return 'Home Delivery';
	}

	function syncOrderSummaryDeliveryLabel( method, pickupStore ) {
		if ( document.querySelector( NATIVE_DELIVERY_STEP_SELECTOR ) ) {
			return;
		}

		const orderSummary = document.querySelector( ORDER_SUMMARY_SELECTOR );

		if ( ! orderSummary ) {
			return;
		}

		const targetLabel = getOrderSummaryDeliveryLabel( method, pickupStore );
		const candidates = orderSummary.querySelectorAll(
			'.wc-block-components-totals-item__label, .wc-block-components-totals-shipping__via'
		);

		Array.from( candidates ).forEach( ( candidate ) => {
			const text = normalizeText( candidate.textContent );

			if (
				! text ||
				( ! text.includes( 'pickup' ) &&
					! text.includes( 'shipping' ) &&
					! text.includes( 'delivery' ) &&
					! text.includes( 'ship' ) &&
					! text.includes( 'collect' ) )
			) {
				return;
			}

			candidate.textContent = targetLabel;
		} );
	}


	function bindNativeDeliveryListeners() {
		getNativeDeliveryOptions().forEach( ( option ) => {
			if ( option.input ) {
				if ( option.input.dataset.devhubDeliveryBound === 'true' ) {
					return;
				}

				option.input.dataset.devhubDeliveryBound = 'true';
				option.input.addEventListener( 'change', () => {
					if ( ! option.input?.checked ) {
						return;
					}

					const nextMethod = getMethodFromNativeOption( option );

					if ( ! isValidMethod( nextMethod ) || getAdditionalFields()[ DELIVERY_FIELD ] === nextMethod ) {
						return;
					}

					patchAdditionalFields( {
						[ DELIVERY_FIELD ]: nextMethod,
						[ PICKUP_FIELD ]: nextMethod === 'pickup' ? getAdditionalFields()[ PICKUP_FIELD ] || '' : '',
					} );
				} );
				return;
			}

			if ( ! option.option || option.option.dataset.devhubDeliveryBound === 'true' ) {
				return;
			}

			option.option.dataset.devhubDeliveryBound = 'true';
			option.option.addEventListener( 'click', () => {
				const nextMethod = getMethodFromNativeOption( option );

				if ( ! isValidMethod( nextMethod ) ) {
					return;
				}

				window.requestAnimationFrame( () => {
					const currentFields = getAdditionalFields();

					if ( currentFields[ DELIVERY_FIELD ] === nextMethod && ( nextMethod === 'pickup' || ! currentFields[ PICKUP_FIELD ] ) ) {
						return;
					}

					patchAdditionalFields( {
						[ DELIVERY_FIELD ]: nextMethod,
						[ PICKUP_FIELD ]: nextMethod === 'pickup' ? currentFields[ PICKUP_FIELD ] || '' : '',
					} );
				} );
			} );
		} );
	}

	function bindNativePickupListeners() {
		getNativePickupOptions().forEach( ( option ) => {
			if ( ! option.input || option.input.dataset.devhubPickupBound === 'true' ) {
				return;
			}

			option.input.dataset.devhubPickupBound = 'true';
			option.input.addEventListener( 'change', () => {
				if ( ! option.input?.checked ) {
					return;
				}

				const matchedLocation = findLocationByNativeText( option.text );
				const currentFields = getAdditionalFields();
				const nextPatch = {
					[ DELIVERY_FIELD ]: 'pickup',
				};

				if ( matchedLocation && currentFields[ PICKUP_FIELD ] !== matchedLocation.value ) {
					nextPatch[ PICKUP_FIELD ] = matchedLocation.value;
				}

				if (
					currentFields[ DELIVERY_FIELD ] === nextPatch[ DELIVERY_FIELD ] &&
					!( PICKUP_FIELD in nextPatch )
				) {
					return;
				}

				patchAdditionalFields( nextPatch );
			} );
		} );
	}

	function syncDefaults() {
		const additionalFields = getAdditionalFields();
		const patch = {};
		const currentMethod = additionalFields[ DELIVERY_FIELD ];
		const nativeStepExists = !! document.querySelector( NATIVE_DELIVERY_STEP_SELECTOR );

		if ( ! isValidMethod( currentMethod ) ) {
			patch[ DELIVERY_FIELD ] = locations.length ? 'home_delivery' : 'home_delivery';
		}

		if ( additionalFields[ DELIVERY_FIELD ] === 'pickup' && ! locations.length ) {
			patch[ DELIVERY_FIELD ] = 'home_delivery';
		}

		if ( Object.keys( patch ).length ) {
			patchAdditionalFields( patch );
			return false;
		}

		const nativeMethod = getSelectedNativeDeliveryMethod();

		if ( nativeStepExists ) {
			if ( isValidMethod( nativeMethod ) && nativeMethod !== additionalFields[ DELIVERY_FIELD ] ) {
				patchAdditionalFields( {
					[ DELIVERY_FIELD ]: nativeMethod,
					[ PICKUP_FIELD ]: nativeMethod === 'pickup' ? additionalFields[ PICKUP_FIELD ] || '' : '',
				} );
				return false;
			}
		} else {
			setPrefersCollection( additionalFields[ DELIVERY_FIELD ] );
			syncNativeDeliverySelection( additionalFields[ DELIVERY_FIELD ] );
		}

		if ( additionalFields[ DELIVERY_FIELD ] === 'pickup' && additionalFields[ PICKUP_FIELD ] ) {
			syncNativePickupSelection( additionalFields[ PICKUP_FIELD ] );
		}

		if ( syncPickupStoreFromNativeSelection() ) {
			return false;
		}

		return true;
	}

	function isCheckoutProcessing() {
		return !! getCheckoutStore()?.isProcessing?.();
	}

	function syncProcessingState( isProcessing ) {
		const orderSummary = document.querySelector( ORDER_SUMMARY_SELECTOR );

		if ( ! orderSummary ) {
			return;
		}

		orderSummary.classList.toggle( 'devhub-checkout-processing', isProcessing );
		orderSummary.setAttribute( 'aria-disabled', isProcessing ? 'true' : 'false' );
	}

	function getCartItems() {
		const cartStore = getCartStore();
		const cartData = cartStore?.getCartData?.();
		const items = cartStore?.getCartItems?.() || cartData?.items || [];

		return Array.isArray( items ) ? items : [];
	}

	function getCartItemKey( item ) {
		return item?.key || item?.cart_item_key || item?.item_key || '';
	}

	function getStoreApiNonce() {
		return (
			window.wc?.wcSettings?.getSetting?.( 'storeApiNonce' ) ||
			window.wcSettings?.storeApiNonce ||
			window.wc_store_api_nonce ||
			''
		);
	}

	function getStoreApiRoot() {
		const root = window.wpApiSettings?.root || `${ window.location.origin }/wp-json/`;
		return root.replace( /\/$/, '' ) + '/wc/store/v1';
	}

	async function removeCheckoutCartItem( cartItemKey, button ) {
		if ( ! cartItemKey || button?.disabled ) {
			return;
		}

		const row = button?.closest?.( ORDER_SUMMARY_ITEM_SELECTOR );
		if ( button ) {
			button.disabled = true;
			button.setAttribute( 'aria-busy', 'true' );
		}
		if ( row ) {
			row.classList.add( 'devhub-checkout-summary-item--removing' );
		}

		try {
			const cartDispatch = getCartDispatch();
			let cartData = null;

			if ( cartDispatch?.removeItemFromCart ) {
				cartData = await cartDispatch.removeItemFromCart( cartItemKey );
			} else if ( window.wp?.apiFetch ) {
				cartData = await window.wp.apiFetch( {
					method: 'POST',
					path: '/wc/store/v1/cart/remove-item',
					data: { key: cartItemKey },
				} );
			} else {
				const response = await window.fetch(
					`${ getStoreApiRoot() }/cart/remove-item`,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							Nonce: getStoreApiNonce(),
						},
						body: JSON.stringify( { key: cartItemKey } ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Unable to remove cart item.' );
				}

				cartData = await response.json();
			}

			if ( cartData && cartDispatch?.receiveCart ) {
				cartDispatch.receiveCart( cartData );
			}

			if ( cartDispatch?.refreshCartItems ) {
				await cartDispatch.refreshCartItems();
			}

			window.wp?.data?.dispatch?.( CART_STORE_KEY )?.invalidateResolutionForStoreSelector?.( 'getCartData' );
			window.wp?.data?.dispatch?.( CART_STORE_KEY )?.invalidateResolutionForStoreSelector?.( 'getCartItems' );
			window.jQuery?.( document.body ).trigger( 'wc_fragment_refresh' );
			window.setTimeout( () => {
				render();
				enhanceOrderSummaryRemoveButtons();
			}, 120 );
		} catch ( error ) {
			if ( button ) {
				button.disabled = false;
				button.removeAttribute( 'aria-busy' );
			}
			if ( row ) {
				row.classList.remove( 'devhub-checkout-summary-item--removing' );
			}
			// Woo Blocks will usually render its own store notice for API errors.
			// Keep this path non-destructive so checkout does not full-page reload.
			window.console?.error?.( error );
		}
	}

	function enhanceOrderSummaryRemoveButtons() {
		const summary = document.querySelector( ORDER_SUMMARY_SELECTOR );
		if ( ! summary ) {
			return;
		}

		const cartItems = getCartItems();
		const rows = Array.from( summary.querySelectorAll( ORDER_SUMMARY_ITEM_SELECTOR ) );

		rows.forEach( ( row, index ) => {
			const cartItemKey = getCartItemKey( cartItems[ index ] );
			if ( ! cartItemKey ) {
				return;
			}

			row.classList.add( 'devhub-checkout-summary-item' );
			row.dataset.devhubCartItemKey = cartItemKey;

			let button = row.querySelector( '.devhub-checkout-summary-remove' );
			if ( ! button ) {
				button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'devhub-checkout-summary-remove';
				button.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
				button.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					event.stopPropagation();
					removeCheckoutCartItem( button.dataset.cartItemKey, button );
				} );
				row.appendChild( button );
			}

			button.dataset.cartItemKey = cartItemKey;
			button.setAttribute( 'aria-label', 'Remove item from order summary' );
		} );
	}

	function setValidationState( method, pickupStore ) {
		const validation = getValidationDispatch();

		if ( ! validation?.setValidationErrors || ! validation?.clearValidationError ) {
			return;
		}

		if ( method === 'pickup' && ! pickupStore ) {
			validation.setValidationErrors( {
				[ DELIVERY_ERROR_KEY ]: {
					message: messages.pickupRequired || 'Please select a pickup store to continue.',
					hidden: false,
				},
			} );
			return;
		}

		validation.clearValidationError( DELIVERY_ERROR_KEY );
	}

	function bindEffectSixButton( button ) {
		if ( ! button || button.dataset.devhubEffectSixBound === 'true' ) {
			return;
		}

		const getOriginalHtml = () => button.dataset.devhubOriginalHtml || button.innerHTML;
		const isDisabled = () => button.disabled || button.getAttribute( 'aria-disabled' ) === 'true';

		button.dataset.devhubEffectSixBound = 'true';

		button.addEventListener( 'mouseover', () => {
			const originalHTML = getOriginalHtml();

			if (
				! originalHTML ||
				isDisabled() ||
				button.classList.contains( 'animating' ) ||
				button.classList.contains( 'mouseover' )
			) {
				return;
			}

			button.classList.add( 'animating', 'mouseover' );

			const tempDiv = document.createElement( 'div' );
			tempDiv.innerHTML = originalHTML;

			const chars = Array.from( tempDiv.childNodes );
			window.setTimeout( () => button.classList.remove( 'animating' ), ( chars.length + 1 ) * 50 );

			const animationType = button.dataset.animation || 'text-spin';
			button.innerHTML = '';

			chars.forEach( ( node ) => {
				if ( node.nodeType === Node.TEXT_NODE ) {
					node.textContent.split( '' ).forEach( ( char ) => {
						button.innerHTML += `<span class="letter">${ char === ' ' ? '&nbsp;' : char }</span>`;
					} );
					return;
				}

				button.innerHTML += `<span class="letter">${ node.outerHTML }</span>`;
			} );

			button.querySelectorAll( '.letter' ).forEach( ( span, index ) => {
				window.setTimeout( () => span.classList.add( animationType ), 50 * index );
			} );
		} );

		button.addEventListener( 'mouseout', () => {
			button.classList.remove( 'mouseover' );
			button.innerHTML = getOriginalHtml();
		} );
	}

	function enhanceActionButton( button, customClass, fallbackText ) {
		if ( ! button ) {
			return;
		}

		button.classList.add( 'wf-btn', 'wf-btn-primary', customClass );

		const text = ( button.textContent || button.getAttribute( 'aria-label' ) || fallbackText ).trim();
		const desiredHtml = `${ text }<i class="fas fa-arrow-right" aria-hidden="true"></i>`;

		if ( button.dataset.devhubOriginalHtml !== desiredHtml ) {
			button.dataset.devhubOriginalHtml = desiredHtml;
		}

		if (
			! button.classList.contains( 'mouseover' ) &&
			! button.className.includes( '--loading' ) &&
			button.innerHTML !== desiredHtml
		) {
			button.innerHTML = desiredHtml;
		}

		bindEffectSixButton( button );
	}

	function enhancePlaceOrderButton() {
		enhanceActionButton(
			document.querySelector( PLACE_ORDER_SELECTOR ),
			'devhub-checkout-place-order-button',
			'Place Order'
		);
	}

	function enhanceCouponButton() {
		enhanceActionButton(
			document.querySelector( COUPON_BUTTON_SELECTOR ),
			'devhub-checkout-coupon-button',
			'Apply'
		);
	}

	function enhanceEmptyCheckoutButton() {
		const button = document.querySelector( EMPTY_CHECKOUT_BUTTON_SELECTOR );

		if ( ! button ) {
			return;
		}

		button.closest( '.wp-block-button' )?.classList.remove( 'btn--effect-six' );
		button.classList.remove( 'wf-btn', 'wf-btn-primary', 'mouseover', 'animating' );
		button.classList.add( 'devhub-empty-checkout-button' );

		if ( button.innerHTML !== 'Browse store <i class="fas fa-arrow-right" aria-hidden="true"></i>' ) {
			button.innerHTML = 'Browse store <i class="fas fa-arrow-right" aria-hidden="true"></i>';
		}
	}

	function enhanceCouponInput() {
		const input = document.querySelector( COUPON_INPUT_SELECTOR );
		const label = document.querySelector( COUPON_INPUT_LABEL_SELECTOR );

		if ( ! input ) {
			return;
		}

		input.placeholder = 'Enter code';

		if ( label ) {
			label.textContent = 'Coupon code';
		}
	}

	function enhanceContactInput() {
		const input = document.querySelector( CONTACT_EMAIL_INPUT_SELECTOR );
		const label = document.querySelector( CONTACT_EMAIL_LABEL_SELECTOR );
		const accountEmail = String( config.accountEmail || '' ).trim();

		if ( ! input ) {
			return;
		}

		input.placeholder = 'Enter email address';

		if ( accountEmail ) {
			input.value = accountEmail;
			input.defaultValue = accountEmail;
			input.readOnly = true;
		}

		if ( label ) {
			label.textContent = 'Email address';
		}
	}

	function getBillingEmail() {
		const additionalFields = getAdditionalFields();
		const customBillingEmail = String( additionalFields[ BILLING_EMAIL_FIELD ] || '' ).trim();
		const configuredBillingEmail = String( config.billingEmail || '' ).trim();
		const cartBillingEmail = String( getCartData()?.billingAddress?.email || '' ).trim();

		return customBillingEmail || configuredBillingEmail || cartBillingEmail;
	}

	function syncInitialBillingEmail() {
		const configuredBillingEmail = String( config.billingEmail || '' ).trim();
		const cartBillingEmail = String( getCartData()?.billingAddress?.email || '' ).trim();
		const customBillingEmail = String( getAdditionalFields()[ BILLING_EMAIL_FIELD ] || '' ).trim();
		const initialBillingEmail = configuredBillingEmail || cartBillingEmail;

		if ( state.billingEmailInitialized || ! initialBillingEmail ) {
			return;
		}

		state.billingEmailInitialized = true;

		if ( customBillingEmail !== initialBillingEmail ) {
			setBillingEmail( initialBillingEmail );
		}
	}

	function setBillingEmail( email ) {
		patchAdditionalFields( {
			[ BILLING_EMAIL_FIELD ]: email,
		} );
	}

	function getBillingStepTitle( step ) {
		return normalizeText(
			step?.querySelector( '.wc-block-components-checkout-step__title' )?.textContent || ''
		);
	}

	function getBillingSteps() {
		return Array.from( document.querySelectorAll( BILLING_STEP_SELECTOR ) ).filter( ( step ) => {
			const title = getBillingStepTitle( step );
			return ! title || title.includes( 'billing address' );
		} );
	}

	function ensureBillingEmailFormField( step ) {
		const form = step.querySelector( BILLING_ADDRESS_FORM_SELECTOR );

		if ( ! form ) {
			return;
		}

		let field = form.querySelector( `.${ BILLING_EMAIL_FIELD_CLASS }` );
		const billingEmail = getBillingEmail();

		if ( ! field ) {
			field = document.createElement( 'div' );
			field.className = `wc-block-components-text-input ${ BILLING_EMAIL_FIELD_CLASS }`;
			field.innerHTML = `
				<input type="email" id="devhub-billing-email" autocomplete="email">
				<label for="devhub-billing-email">Email address</label>
			`;
			form.appendChild( field );

			field.querySelector( 'input' )?.addEventListener( 'input', ( event ) => {
				setBillingEmail( event.target.value );
			} );
		}

		const input = field.querySelector( 'input' );
		if ( input && document.activeElement !== input && input.value !== billingEmail ) {
			input.value = billingEmail;
		}
	}

	function enhanceBillingEmailField() {
		syncInitialBillingEmail();

		getBillingSteps().forEach( ( step ) => {
			ensureBillingEmailFormField( step );
			step.querySelectorAll( `.${ BILLING_EMAIL_FIELD_CLASS }` ).forEach( ( field ) => {
				field.style.display = step.querySelector( BILLING_ADDRESS_FORM_SELECTOR ) ? '' : 'none';
			} );
		} );
	}

	function expandAddressLineTwo() {
		document.querySelectorAll( ADDRESS_LINE_2_TOGGLE_SELECTOR ).forEach( ( toggle ) => {
			if ( toggle instanceof HTMLElement ) {
				toggle.click();
			}
		} );
	}

	function shouldUseCheckoutSidebar() {
		return typeof window.matchMedia !== 'function' || window.matchMedia( DESKTOP_SIDEBAR_MEDIA ).matches;
	}

	function isElementVisible( element ) {
		return !! ( element && ( element.offsetParent !== null || element.getClientRects().length ) );
	}

	function getVisibleOrderSummaryBlock() {
		const blocks = Array.from(
			document.querySelectorAll( '.wp-block-woocommerce-checkout-order-summary-block' )
		);

		return blocks.find( ( block ) => isElementVisible( block ) ) || blocks[ 0 ] || null;
	}

	function syncSidebarRelocationState() {
		if ( ! document.body ) {
			return;
		}

		document.body.classList.toggle(
			SIDEBAR_RELOCATION_CLASS,
			!! document.querySelector( '.wc-block-checkout, .wp-block-woocommerce-checkout' ) && shouldUseCheckoutSidebar()
		);
	}

	function findOrderNoteStep() {
		const candidates = Array.from(
			document.querySelectorAll(
				'.wc-block-components-checkout-step, .wp-block-woocommerce-checkout-order-note-block, .wc-block-checkout__additional-fields'
			)
		);

		return candidates.find( ( candidate ) => {
			if ( ! candidate ) {
				return false;
			}

			const headingText = normalizeText(
				candidate.querySelector( '.wc-block-components-checkout-step__title, .wc-block-components-checkbox__label' )?.textContent || ''
			);
			const textarea = candidate.querySelector( 'textarea' );
			const placeholderText = normalizeText( textarea?.getAttribute( 'placeholder' ) || '' );

			return (
				headingText.includes( 'add a note to your order' ) ||
				placeholderText.includes( 'notes about your order' )
			);
		} ) || null;
	}

	function ensureOrderNotePlaceholder( noteStep ) {
		if ( ! noteStep || ! noteStep.parentElement ) {
			return null;
		}

		let placeholder = document.querySelector( ORDER_NOTE_PLACEHOLDER_SELECTOR );

		if ( placeholder ) {
			return placeholder;
		}

		placeholder = document.createElement( 'div' );
		placeholder.className = 'devhub-checkout-order-note-placeholder';
		placeholder.hidden = true;
		noteStep.parentElement.insertBefore( placeholder, noteStep );

		return placeholder;
	}

	function ensurePaymentPlaceholder( paymentStep ) {
		if ( ! paymentStep || ! paymentStep.parentElement ) {
			return null;
		}

		let placeholder = document.querySelector( PAYMENT_PLACEHOLDER_SELECTOR );

		if ( placeholder ) {
			return placeholder;
		}

		placeholder = document.createElement( 'div' );
		placeholder.className = 'devhub-checkout-payment-placeholder';
		placeholder.hidden = true;
		paymentStep.parentElement.insertBefore( placeholder, paymentStep );

		return placeholder;
	}

	function moveOrderNoteStep() {
		const noteStep = findOrderNoteStep();
		if ( ! noteStep ) {
			return;
		}

		const placeholder = ensureOrderNotePlaceholder( noteStep );
		const orderSummary = getVisibleOrderSummaryBlock();
		const targetParent = orderSummary?.parentElement || null;

		noteStep.classList.add( 'devhub-checkout-order-note-step' );

		if ( orderSummary && targetParent ) {
			if ( noteStep.parentElement !== targetParent || noteStep.previousElementSibling !== orderSummary ) {
				orderSummary.insertAdjacentElement( 'afterend', noteStep );
			}
			return;
		}

		if ( placeholder?.parentElement && noteStep.previousElementSibling !== placeholder ) {
			placeholder.insertAdjacentElement( 'afterend', noteStep );
		}
	}

	function movePaymentStep() {
		const paymentStep = document.querySelector( PAYMENT_STEP_SELECTOR );
		if ( ! paymentStep ) {
			return;
		}

		const placeholder = ensurePaymentPlaceholder( paymentStep );
		const orderSummary = getVisibleOrderSummaryBlock();
		const noteStep = document.querySelector( '.devhub-checkout-order-note-step' );
		const targetParent = orderSummary?.parentElement || null;

		paymentStep.classList.add( 'devhub-checkout-payment-step' );

		if ( orderSummary && targetParent ) {
			const anchor = noteStep || orderSummary;

			if ( anchor && ( paymentStep.parentElement !== targetParent || paymentStep.previousElementSibling !== anchor ) ) {
				anchor.insertAdjacentElement( 'afterend', paymentStep );
			}
			return;
		}

		if ( placeholder?.parentElement && paymentStep.previousElementSibling !== placeholder ) {
			placeholder.insertAdjacentElement( 'afterend', paymentStep );
		}
	}

	function render() {
		syncSidebarRelocationState();

		if ( ! syncDefaults() ) {
			return;
		}

		const additionalFields = getAdditionalFields();
		const method = isValidMethod( additionalFields[ DELIVERY_FIELD ] ) ? additionalFields[ DELIVERY_FIELD ] : 'home_delivery';
		const pickupStore = additionalFields[ PICKUP_FIELD ] || '';
		const isProcessing = isCheckoutProcessing();

		bindNativeDeliveryListeners();
		bindNativePickupListeners();

		const signature = JSON.stringify( {
			method,
			pickupStore,
			locationCount: locations.length,
			isProcessing,
		} );

		if ( signature === lastSignature ) {
			syncProcessingState( isProcessing );
			syncOrderSummaryDeliveryLabel( method, pickupStore );
			syncBillingTitleForPickup( method );
			enhanceOrderSummaryRemoveButtons();
			return;
		}

		lastSignature = signature;
		setValidationState( method, pickupStore );
		syncProcessingState( isProcessing );
		syncOrderSummaryDeliveryLabel( method, pickupStore );
		syncBillingTitleForPickup( method );
		enhancePlaceOrderButton();
		enhanceCouponButton();
		enhanceEmptyCheckoutButton();
		enhanceCouponInput();
		enhanceContactInput();
		enhanceOrderSummaryRemoveButtons();
		expandAddressLineTwo();
		moveOrderNoteStep();
		movePaymentStep();
	}

	function syncBillingTitleForPickup( method ) {
		const billingTitle = document.querySelector(
			'.wc-block-checkout__billing-address .wc-block-components-checkout-step__title, ' +
			'.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__title'
		);

		if ( ! billingTitle ) {
			return;
		}

		const targetTitle = method === 'pickup' ? 'Billing address' : 'Shipping address';

		if ( billingTitle.textContent.trim() !== targetTitle ) {
			billingTitle.textContent = targetTitle;
		}
	}

	function relabelAddressBlocks() {
		const additionalFields = getAdditionalFields();
		const nativeMethod = getSelectedNativeDeliveryMethod();
		const method = isValidMethod( nativeMethod )
			? nativeMethod
			: ( isValidMethod( additionalFields[ DELIVERY_FIELD ] ) ? additionalFields[ DELIVERY_FIELD ] : 'home_delivery' );
		// Shipping-fields block is always visible and shown first → call it "Billing address"
		const shippingTitle = document.querySelector(
			'.wc-block-checkout__shipping-fields .wc-block-components-checkout-step__title'
		);
		if ( shippingTitle && shippingTitle.textContent.trim() !== 'Billing address' ) {
			shippingTitle.textContent = 'Billing address';
		}

		// Billing-address block appears when addresses differ → call it "Shipping address"
		const billingTitle = document.querySelector(
			'.wc-block-checkout__billing-address .wc-block-components-checkout-step__title, ' +
			'.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__title'
		);
		if ( billingTitle ) {
			const targetTitle = method === 'pickup' ? 'Billing address' : 'Shipping address';
			if ( billingTitle.textContent.trim() !== targetTitle ) {
				billingTitle.textContent = targetTitle;
			}
		}

		// Change checkbox label from "Use same address for billing" → "Use same address for shipping"
		document
			.querySelectorAll( '.wc-block-checkout__shipping-fields .wc-block-components-checkbox__label' )
			.forEach( ( label ) => {
				if ( /billing/i.test( label.textContent ) ) {
					label.textContent = label.textContent.replace( /billing/gi, 'shipping' );
				}
			} );
	}

	function enforceTermsMessage() {
		const placeOrderBtn = document.querySelector( PLACE_ORDER_SELECTOR );
		if ( ! placeOrderBtn ) return;

		placeOrderBtn.addEventListener( 'click', () => {
			const termsBlock = document.querySelector( '.wc-block-checkout__terms' );
			if ( ! termsBlock ) return;

			const checkbox = termsBlock.querySelector( 'input[type="checkbox"]' );
			const existing = termsBlock.querySelector( '.devhub-terms-error' );

			if ( checkbox && ! checkbox.checked ) {
				if ( ! existing ) {
					const wrapper = document.createElement( 'div' );
					wrapper.className = 'devhub-terms-error';
					wrapper.setAttribute( 'role', 'alert' );
					const p = document.createElement( 'p' );
					p.innerHTML =
						'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
						'<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17v-6h1.5v6H11zm0-8V7.5h1.5V9H11z"/>' +
						'</svg>' +
						'Please accept the Terms and Conditions to continue.';
					wrapper.appendChild( p );
					termsBlock.appendChild( wrapper );
				}
			} else if ( existing ) {
				existing.remove();
			}

			if ( checkbox ) {
				checkbox.addEventListener( 'change', () => {
					const err = termsBlock.querySelector( '.devhub-terms-error' );
					if ( err ) err.remove();
				}, { once: true } );
			}
		} );
	}

	function boot() {
		if ( ! document.querySelector( '.wc-block-checkout, .wp-block-woocommerce-checkout' ) ) {
			return;
		}

		if ( ! window.wp?.data || ! window.wc?.wcBlocksData ) {
			window.setTimeout( boot, 150 );
			return;
		}

		syncSidebarRelocationState();
		render();
		enhancePlaceOrderButton();
		enhanceCouponButton();
		enhanceCouponInput();
		enhanceContactInput();
		enhanceOrderSummaryRemoveButtons();
		expandAddressLineTwo();
		relabelAddressBlocks();
		enhanceBillingEmailField();
		moveOrderNoteStep();
		movePaymentStep();
		enforceTermsMessage();

		if ( ! hasBoundViewportListener ) {
			hasBoundViewportListener = true;
			window.addEventListener( 'resize', () => {
				syncSidebarRelocationState();
				moveOrderNoteStep();
				movePaymentStep();
			}, { passive: true } );
		}

		if ( unsubscribe ) {
			return;
		}

		unsubscribe = window.wp.data.subscribe( () => {
			render();
			enhancePlaceOrderButton();
			enhanceCouponButton();
			enhanceEmptyCheckoutButton();
			enhanceCouponInput();
			enhanceContactInput();
			enhanceOrderSummaryRemoveButtons();
			expandAddressLineTwo();
			relabelAddressBlocks();
			enhanceBillingEmailField();
			moveOrderNoteStep();
			movePaymentStep();
		} );
	}

	function enhanceOrderConfirmationDate() {
		if ( ! window.devhubOrderTime ) return true;

		const dateEl = document.querySelector(
			'li.woocommerce-order-overview__date strong'
		);
		if ( ! dateEl ) return false;
		if ( dateEl.dataset.devhubEnhanced ) return true;

		dateEl.textContent = dateEl.textContent + ', ' + window.devhubOrderTime;

		// Rename the label — could be a text node or a <p> child
		const dateLi = dateEl.closest( 'li.woocommerce-order-overview__date' );
		if ( dateLi ) {
			// WC Blocks: label in a <p> child
			const titleP = dateLi.querySelector( 'p' );
			if ( titleP ) {
				titleP.textContent = 'Date & Time:';
			} else {
				// Classic template: label is a raw text node
				for ( const node of dateLi.childNodes ) {
					if ( node.nodeType === Node.TEXT_NODE && node.textContent.trim() ) {
						node.textContent = ' Date & Time: ';
						break;
					}
				}
			}
		}

		dateEl.dataset.devhubEnhanced = '1';
		return true;
	}

	function initOrderConfirmationDate() {
		if ( enhanceOrderConfirmationDate() ) return;
		const observer = new MutationObserver( () => {
			if ( enhanceOrderConfirmationDate() ) {
				observer.disconnect();
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	syncSidebarRelocationState();

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			boot();
			initOrderConfirmationDate();
		} );
	} else {
		boot();
		initOrderConfirmationDate();
	}
}() );
