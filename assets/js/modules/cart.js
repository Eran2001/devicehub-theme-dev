( function () {
	'use strict';

	const COUPON_BUTTON_SELECTOR = '.wc-block-cart__sidebar .wc-block-components-totals-coupon__button';
	const COUPON_INPUT_SELECTOR = '.wc-block-cart__sidebar .wc-block-components-totals-coupon__input input';
	const CHECKOUT_BUTTON_SELECTOR = '.wc-block-cart__submit-button.wc-block-components-button';
	const PRODUCT_CARD_BUTTON_SELECTOR = '.wc-block-cart .wc-block-components-product-button__button.add_to_cart_button';
	const DISCOUNT_CHIP_SELECTORS = [
		'.wc-block-cart__sidebar .wc-block-components-totals-discount__coupon-list-item',
		'.wc-block-cart__sidebar .wc-block-components-totals-discount .wc-block-components-chip',
		'.wc-block-cart__sidebar .wc-block-components-totals-discount__coupon-list-item .wc-block-components-chip__text',
		'.wc-block-cart__sidebar .wc-block-components-totals-discount .wc-block-components-chip__text',
	];

	function updateDiscountChipElement( element, desiredLabel ) {
		if ( ! element ) {
			return;
		}

		const textNodeTarget = element.querySelector( '.wc-block-components-chip__text' );

		if ( textNodeTarget ) {
			if ( normalizeText( textNodeTarget.textContent ) !== normalizeText( desiredLabel ) ) {
				textNodeTarget.textContent = desiredLabel;
			}
			return;
		}

		const removeButton = element.querySelector( '.wc-block-components-chip__remove, .wc-block-components-chip__remove-icon, button, svg' );
		const currentLabel = normalizeText( element.textContent.replace( /\s*[×x]\s*$/, '' ) );

		if ( currentLabel === normalizeText( desiredLabel ) ) {
			return;
		}

		if ( removeButton && removeButton.parentNode === element ) {
			element.textContent = desiredLabel + ' ';
			element.appendChild( removeButton );
			return;
		}

		element.textContent = desiredLabel;
	}

	function normalizeText( value ) {
		return String( value || '' ).trim().replace( /\s+/g, ' ' ).toLowerCase();
	}

	let discountSummaryTimer = null;
	let discountSummaryRequest = null;

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

	function enhanceCouponButton() {
		const button = document.querySelector( COUPON_BUTTON_SELECTOR );

		if ( ! button ) {
			return;
		}

		enhanceActionButton( button, 'devhub-cart-coupon-button', 'Apply' );
	}

	function enhanceCheckoutButton() {
		const button = document.querySelector( CHECKOUT_BUTTON_SELECTOR );

		if ( ! button ) {
			return;
		}

		enhanceActionButton( button, 'devhub-cart-submit-button', 'Proceed to Checkout' );
	}

	function enhanceActionButton( button, customClass, fallbackText ) {
		if ( ! button ) {
			return;
		}

		const text = ( button.textContent || button.getAttribute( 'aria-label' ) || fallbackText ).trim();
		const desiredHtml = `${ text }<i class="fas fa-arrow-right" aria-hidden="true"></i>`;

		button.classList.add( 'wf-btn', 'wf-btn-primary', customClass );

		if ( button.dataset.devhubOriginalHtml !== desiredHtml ) {
			button.dataset.devhubOriginalHtml = desiredHtml;
		}

		if (
			! button.classList.contains( 'mouseover' ) &&
			! button.className.includes( 'loading' ) &&
			button.innerHTML !== desiredHtml
		) {
			button.innerHTML = desiredHtml;
		}

		bindEffectSixButton( button );
	}

	function enhanceProductCardButtons() {
		document.querySelectorAll( PRODUCT_CARD_BUTTON_SELECTOR ).forEach( ( button ) => {
			button.closest( '.wp-block-button' )?.classList.add( 'btn--effect-six' );
			enhanceActionButton( button, 'devhub-cart-product-button', 'Add to cart' );
		} );
	}

	function replaceDiscountChipLabel() {
		const summary = window.devhubCartData?.discountSummary || {};
		const desiredLabel = String( summary.chip_label || '' ).trim();

		if ( ! desiredLabel ) {
			return;
		}

		DISCOUNT_CHIP_SELECTORS.forEach( ( selector ) => {
			document.querySelectorAll( selector ).forEach( ( element ) => {
				updateDiscountChipElement( element, desiredLabel );
			} );
		} );
	}

	function requestDiscountSummaryRefresh() {
		if ( ! window.devhubConfig?.ajaxUrl ) {
			return;
		}

		if ( discountSummaryRequest ) {
			return;
		}

		var payload = new window.URLSearchParams( { action: 'devhub_cart_discount_summary' } );

		discountSummaryRequest = window.fetch( window.devhubConfig.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			credentials: 'same-origin',
			body: payload.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success || ! result.data ) {
					return;
				}

				window.devhubCartData = window.devhubCartData || {};
				window.devhubCartData.discountSummary = result.data.discountSummary || {};
				window.devhubCartData.virtualCouponLabel = result.data.virtualCouponLabel || window.devhubCartData.virtualCouponLabel;
				replaceDiscountChipLabel();
			} )
			.catch( function () {
				return null;
			} )
			.finally( function () {
				discountSummaryRequest = null;
			} );
	}

	function scheduleDiscountSummaryRefresh() {
		window.clearTimeout( discountSummaryTimer );
		discountSummaryTimer = window.setTimeout( requestDiscountSummaryRefresh, 180 );
	}

	function scheduleEnhance() {
		window.setTimeout( enhanceCouponButton, 0 );
		window.setTimeout( enhanceCheckoutButton, 0 );
		window.setTimeout( enhanceProductCardButtons, 0 );
		window.setTimeout( replaceDiscountChipLabel, 0 );
		window.setTimeout( enhanceCouponButton, 120 );
		window.setTimeout( enhanceCheckoutButton, 120 );
		window.setTimeout( enhanceProductCardButtons, 120 );
		window.setTimeout( replaceDiscountChipLabel, 120 );
		scheduleDiscountSummaryRefresh();
	}

	function initHeaderCartFragmentBridge() {
		const cart = document.querySelector( '.wc-block-cart' );
		const $ = window.jQuery;

		if ( ! cart || ! $ || ! document.body ) {
			return;
		}

		let refreshTimer = null;
		const requestRefresh = () => {
			window.clearTimeout( refreshTimer );
			refreshTimer = window.setTimeout( () => {
				$( document.body ).trigger( 'wc_fragment_refresh' );
			}, 500 );
		};

		document.body.addEventListener( 'wc-blocks_removed_from_cart', requestRefresh );
		document.body.addEventListener( 'wc-blocks_added_to_cart', requestRefresh );

		cart.addEventListener( 'click', ( event ) => {
			if (
				event.target.closest( '.wc-block-cart-item__remove-link' ) ||
				event.target.closest( '.wc-block-components-quantity-selector__button' )
			) {
				requestRefresh();
			}
		} );

		cart.addEventListener( 'change', ( event ) => {
			if ( event.target.closest( '.wc-block-components-quantity-selector__input' ) ) {
				requestRefresh();
			}
		} );
	}

	function initCartSidebarObserver() {
		const sidebar = document.querySelector( '.wc-block-cart__sidebar' );

		if ( ! sidebar || sidebar.dataset.devhubObserverBound === 'true' ) {
			return;
		}

		sidebar.dataset.devhubObserverBound = 'true';

		const observer = new MutationObserver( () => {
			scheduleEnhance();
		} );

		observer.observe( sidebar, {
			childList: true,
			subtree: true,
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			scheduleEnhance();
			initHeaderCartFragmentBridge();
			initCartSidebarObserver();
		} );
	} else {
		scheduleEnhance();
		initHeaderCartFragmentBridge();
		initCartSidebarObserver();
	}

	document.addEventListener( 'click', ( event ) => {
		if (
			event.target.closest( '.wc-block-components-panel__button' ) ||
			event.target.closest( COUPON_BUTTON_SELECTOR ) ||
			event.target.closest( PRODUCT_CARD_BUTTON_SELECTOR )
		) {
			scheduleEnhance();
		}
	} );

	document.addEventListener( 'input', ( event ) => {
		if ( event.target.matches( COUPON_INPUT_SELECTOR ) ) {
			scheduleEnhance();
		}
	} );
}() );
