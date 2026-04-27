( function () {
	'use strict';

	const COUPON_BUTTON_SELECTOR = '.wc-block-cart__sidebar .wc-block-components-totals-coupon__button';
	const COUPON_INPUT_SELECTOR = '.wc-block-cart__sidebar .wc-block-components-totals-coupon__input input';
	const CHECKOUT_BUTTON_SELECTOR = '.wc-block-cart__submit-button.wc-block-components-button';
	const PRODUCT_CARD_BUTTON_SELECTOR = '.wc-block-cart .wc-block-components-product-button__button.add_to_cart_button';

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

	function scheduleEnhance() {
		window.setTimeout( enhanceCouponButton, 0 );
		window.setTimeout( enhanceCheckoutButton, 0 );
		window.setTimeout( enhanceProductCardButtons, 0 );
		window.setTimeout( enhanceCouponButton, 120 );
		window.setTimeout( enhanceCheckoutButton, 120 );
		window.setTimeout( enhanceProductCardButtons, 120 );
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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			scheduleEnhance();
			initHeaderCartFragmentBridge();
		} );
	} else {
		scheduleEnhance();
		initHeaderCartFragmentBridge();
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
