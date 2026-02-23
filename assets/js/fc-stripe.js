/**
 * Flavor Commerce — Stripe Payment Element Integration
 *
 * Uses Stripe Payment Element (not Card Element) to support ALL payment methods:
 * - Cards (Visa, Mastercard, Amex, etc.)
 * - BLIK (Poland)
 * - Przelewy24 (Poland)
 * - Apple Pay / Google Pay
 * - iDEAL, Bancontact, SEPA, Sofort, Giropay
 * - and more — automatically based on customer location & Stripe Dashboard settings
 *
 * Flow:
 * 1. When step 3 (Payment) activates and Stripe is selected → create PaymentIntent via AJAX
 * 2. Create Stripe Elements with clientSecret → mount Payment Element
 * 3. On form submit → stripe.confirmPayment() with return_url
 * 4. Stripe handles redirect-based methods (BLIK, P24, iDEAL) automatically
 * 5. Customer returns to thank-you page → webhook confirms + page verifies status
 */
(function ($) {
  "use strict";

  if (typeof fc_stripe_params === "undefined") return;

  var stripe = Stripe(fc_stripe_params.publishable_key, {
    locale: fc_stripe_params.locale || "auto",
  });

  var stripeElements = null;
  var paymentElement = null;
  var paymentElementMounted = false;
  var paymentIntentSecret = null;
  var paymentIntentId = null;
  var intentCreating = false;

  /* ── Create Payment Intent & mount Payment Element ─────── */

  function initPaymentElement() {
    if (paymentElementMounted || intentCreating) return;

    var mountPoint = document.getElementById("fc-stripe-payment-element");
    if (!mountPoint) return;

    intentCreating = true;

    // Show loading state
    mountPoint.innerHTML =
      '<div class="fc-stripe-loading"><div class="fc-spinner"></div></div>';

    // Get current form data for the intent
    var $form = $(".fc-checkout-form");
    var shippingCost = 0;
    var $selectedShipping = $('input[name="shipping_method"]:checked');
    if ($selectedShipping.length) {
      shippingCost = parseFloat($selectedShipping.data("cost")) || 0;
    }

    $.ajax({
      url: fc_stripe_params.ajax_url,
      method: "POST",
      data: {
        action: "fc_stripe_create_intent",
        nonce: fc_stripe_params.nonce,
        shipping_cost: shippingCost,
        billing_email: $form.find('[name="billing_email"]').val() || "",
      },
      success: function (res) {
        intentCreating = false;

        if (!res.success) {
          mountPoint.innerHTML =
            '<div class="fc-stripe-element-error">' +
            escapeHtml(res.data ? res.data.message : "Error") +
            "</div>";
          return;
        }

        paymentIntentSecret = res.data.clientSecret;
        paymentIntentId = res.data.intentId;

        // Create Elements instance with the client secret
        var appearance = {
          theme: "stripe",
          variables: {
            colorPrimary: "#d4a843",
            colorBackground: "#ffffff",
            colorText: "#1d2327",
            colorDanger: "#e74c3c",
            fontFamily:
              '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            fontSizeBase: "15px",
            borderRadius: "6px",
            spacingUnit: "4px",
          },
          rules: {
            ".Input:focus": {
              borderColor: "#d4a843",
              boxShadow: "0 0 0 3px rgba(212, 168, 67, 0.15)",
            },
          },
        };

        stripeElements = stripe.elements({
          clientSecret: paymentIntentSecret,
          appearance: appearance,
          locale: fc_stripe_params.locale || "auto",
        });

        // Create and mount Payment Element
        paymentElement = stripeElements.create("payment", {
          layout: {
            type: "tabs",
            defaultCollapsed: false,
          },
          defaultValues: {
            billingDetails: getBillingDetails(),
          },
          fields: {
            billingDetails: {
              name: "never",
              email: "never",
              phone: "never",
              address: {
                country: "never",
              },
            },
          },
        });

        mountPoint.innerHTML = "";
        paymentElement.mount("#fc-stripe-payment-element");
        paymentElementMounted = true;

        // Listen for errors
        paymentElement.on("change", function (event) {
          var errEl = document.getElementById("fc-stripe-errors");
          if (errEl) {
            errEl.textContent = event.error ? event.error.message : "";
          }
        });
      },
      error: function () {
        intentCreating = false;
        mountPoint.innerHTML =
          '<div class="fc-stripe-element-error">' +
          escapeHtml(fc_stripe_params.i18n.generic_error) +
          "</div>";
      },
    });
  }

  /* ── Get billing details from the form ─────────────────── */

  function getBillingDetails() {
    var $form = $(".fc-checkout-form");
    var accountType =
      $form.find('[name="account_type"]:checked').val() || "private";
    var name;
    if (accountType === "company") {
      name = ($form.find('[name="billing_company"]').val() || "").trim();
      if (!name) {
        name = (
          ($form.find('[name="billing_first_name"]').val() || "") +
          " " +
          ($form.find('[name="billing_last_name"]').val() || "")
        ).trim();
      }
    } else {
      name = (
        ($form.find('[name="billing_first_name"]').val() || "") +
        " " +
        ($form.find('[name="billing_last_name"]').val() || "")
      ).trim();
    }
    return {
      name: name,
      email: $form.find('[name="billing_email"]').val() || "",
      phone: $form.find('[name="billing_phone"]').val() || "",
      address: {
        line1: $form.find('[name="billing_address"]').val() || "",
        city: $form.find('[name="billing_city"]').val() || "",
        postal_code: $form.find('[name="billing_postcode"]').val() || "",
        country: $form.find('[name="billing_country"]').val() || "PL",
      },
    };
  }

  /* ── Toggle Payment Element visibility ─────────────────── */

  function toggleStripePayment() {
    var wrapper = $("#fc-stripe-payment-wrapper");
    if (!wrapper.length) return;

    var selected = $('input[name="payment_method"]:checked').val();

    if (selected === "stripe") {
      wrapper.addClass("active");
      if (!paymentElementMounted && !intentCreating) {
        initPaymentElement();
      }
    } else {
      wrapper.removeClass("active");
    }
  }

  // Listen for payment method changes
  $(document).on("change", 'input[name="payment_method"]', toggleStripePayment);

  // When billing country changes, re-create Payment Element so Stripe shows correct methods
  $(document).on("change", '[name="billing_country"]', function () {
    if (!paymentElementMounted || !stripeElements) return;

    // Update the Payment Element with new billing details (including new country)
    if (paymentElement) {
      paymentElement.update({
        defaultValues: {
          billingDetails: getBillingDetails(),
        },
      });
    }

    // Destroy and re-mount to force Stripe to re-evaluate available payment methods
    paymentElement.unmount();
    paymentElement.destroy();
    paymentElementMounted = false;
    paymentElement = null;

    paymentElement = stripeElements.create("payment", {
      layout: {
        type: "tabs",
        defaultCollapsed: false,
      },
      defaultValues: {
        billingDetails: getBillingDetails(),
      },
      fields: {
        billingDetails: {
          name: "never",
          email: "never",
          phone: "never",
          address: {
            country: "never",
          },
        },
      },
    });

    paymentElement.mount("#fc-stripe-payment-element");
    paymentElementMounted = true;

    paymentElement.on("change", function (event) {
      var errEl = document.getElementById("fc-stripe-errors");
      if (errEl) {
        errEl.textContent = event.error ? event.error.message : "";
      }
    });
  });

  // Initialize on DOM ready
  $(function () {
    toggleStripePayment();
  });

  // Also re-check when step 3 (Payment) becomes active
  // and refresh billing details so Stripe has current name/email
  $(document).on("click", ".fc-step-next, .fc-step-prev", function () {
    setTimeout(function () {
      toggleStripePayment();
      if (paymentElement && paymentElementMounted) {
        paymentElement.update({
          defaultValues: {
            billingDetails: getBillingDetails(),
          },
        });
      }
    }, 200);
  });

  /* ── Intercept checkout form submission ────────────────── */

  $(document).on("submit", ".fc-checkout-form", function (e) {
    var selected = $('input[name="payment_method"]:checked').val();

    // Only intercept for Stripe payments
    if (selected !== "stripe") return true;

    // Don't intercept if we already have a confirmed payment
    if ($(this).data("fc-stripe-confirmed")) return true;

    e.preventDefault();

    var $form = $(this);
    var $btn = $form.find(".fc-btn-checkout");

    if (!paymentElementMounted || !paymentIntentSecret) {
      handleError(fc_stripe_params.i18n.generic_error);
      return;
    }

    // Disable submit button
    $btn.prop("disabled", true);
    $form.addClass("fc-stripe-processing");

    // Show processing overlay
    showOverlay(fc_stripe_params.i18n.processing);

    // First submit the form via AJAX to create the order, then confirm payment
    // This way we have an order_id to pass as return_url param
    var formData = $form.serialize();
    formData += "&fc_stripe_intent_id=" + encodeURIComponent(paymentIntentId);
    formData += "&action=fc_stripe_checkout";

    $.ajax({
      url: fc_stripe_params.ajax_url,
      method: "POST",
      data: formData,
      success: function (res) {
        if (res.success && res.data && res.data.order_id) {
          confirmPayment($form, $btn, res.data);
        } else {
          // Order creation failed
          handleError(
            res.data ? res.data.message : fc_stripe_params.i18n.generic_error,
          );
          $btn.prop("disabled", false);
          $form.removeClass("fc-stripe-processing");
        }
      },
      error: function () {
        // Fallback: confirm payment directly, order will be matched by webhook
        confirmPayment($form, $btn, null);
      },
    });
  });

  /* ── Confirm payment with Stripe ───────────────────────── */

  function confirmPayment($form, $btn, orderData) {
    // Build return URL (thank-you page)
    var returnUrl = fc_stripe_params.return_url || window.location.origin;

    if (orderData && orderData.order_id) {
      returnUrl +=
        (returnUrl.indexOf("?") !== -1 ? "&" : "?") +
        "order_id=" +
        orderData.order_id +
        "&stripe=1";
      if (orderData.token) {
        returnUrl += "&token=" + orderData.token;
      }
    }

    stripe
      .confirmPayment({
        elements: stripeElements,
        confirmParams: {
          return_url: returnUrl,
          payment_method_data: {
            billing_details: getBillingDetails(),
          },
        },
        redirect: "if_required",
      })
      .then(function (result) {
        if (result.error) {
          // Payment failed — notify server (keeps pending_payment), then redirect to retry page
          if (orderData && orderData.order_id) {
            var failData = {
              action: "fc_stripe_payment_failed",
              nonce: fc_stripe_params.nonce,
              order_id: orderData.order_id,
              intent_id: paymentIntentId || "",
            };
            if (orderData.token) {
              failData.order_token = orderData.token;
            }
            var guestTokenParam = orderData.token
              ? "&token=" + orderData.token
              : "";
            $.ajax({
              url: fc_stripe_params.ajax_url,
              method: "POST",
              data: failData,
              success: function (response) {
                removeOverlay();
                if (
                  response.success &&
                  response.data &&
                  response.data.retry_url
                ) {
                  window.location.href = response.data.retry_url;
                } else {
                  var retryBase =
                    fc_stripe_params.retry_url || fc_stripe_params.return_url;
                  var sep = retryBase.indexOf("?") !== -1 ? "&" : "?";
                  window.location.href =
                    retryBase +
                    sep +
                    "order_id=" +
                    orderData.order_id +
                    guestTokenParam;
                }
              },
              error: function () {
                removeOverlay();
                var retryBase =
                  fc_stripe_params.retry_url || fc_stripe_params.return_url;
                var sep = retryBase.indexOf("?") !== -1 ? "&" : "?";
                window.location.href =
                  retryBase +
                  sep +
                  "order_id=" +
                  orderData.order_id +
                  guestTokenParam;
              },
            });
          } else {
            handleError(result.error.message);
            $btn.prop("disabled", false);
            $form.removeClass("fc-stripe-processing");
          }
        } else if (
          result.paymentIntent &&
          result.paymentIntent.status === "succeeded"
        ) {
          // Payment succeeded without redirect (cards, Apple Pay, etc.)
          updateOverlay(fc_stripe_params.i18n.payment_success);

          // Redirect to thank-you page
          setTimeout(function () {
            window.location.href = returnUrl;
          }, 600);
        } else if (
          result.paymentIntent &&
          result.paymentIntent.status === "requires_action"
        ) {
          // 3DS or additional action required — Stripe.js handles this automatically
          // If we reach here, the action was cancelled — redirect to retry page
          if (orderData && orderData.order_id) {
            var failData3ds = {
              action: "fc_stripe_payment_failed",
              nonce: fc_stripe_params.nonce,
              order_id: orderData.order_id,
              intent_id: paymentIntentId || "",
            };
            if (orderData.token) {
              failData3ds.order_token = orderData.token;
            }
            var guestTokenParam3ds = orderData.token
              ? "&token=" + orderData.token
              : "";
            $.ajax({
              url: fc_stripe_params.ajax_url,
              method: "POST",
              data: failData3ds,
              success: function (response) {
                removeOverlay();
                if (
                  response.success &&
                  response.data &&
                  response.data.retry_url
                ) {
                  window.location.href = response.data.retry_url;
                } else {
                  var retryBase =
                    fc_stripe_params.retry_url || fc_stripe_params.return_url;
                  var sep = retryBase.indexOf("?") !== -1 ? "&" : "?";
                  window.location.href =
                    retryBase +
                    sep +
                    "order_id=" +
                    orderData.order_id +
                    guestTokenParam3ds;
                }
              },
              error: function () {
                removeOverlay();
                var retryBase =
                  fc_stripe_params.retry_url || fc_stripe_params.return_url;
                var sep = retryBase.indexOf("?") !== -1 ? "&" : "?";
                window.location.href =
                  retryBase +
                  sep +
                  "order_id=" +
                  orderData.order_id +
                  guestTokenParam3ds;
              },
            });
          } else {
            handleError(fc_stripe_params.i18n.payment_error);
            $btn.prop("disabled", false);
            $form.removeClass("fc-stripe-processing");
          }
        } else {
          // Other status — payment might still be processing
          updateOverlay(fc_stripe_params.i18n.processing);
          setTimeout(function () {
            window.location.href = returnUrl;
          }, 1000);
        }
      });
  }

  /* ── Thank-you page: verify payment status ─────────────── */

  $(function () {
    var urlParams = new URLSearchParams(window.location.search);

    // redirect_status=failed is handled server-side via template_redirect
    // — no JS handler needed, PHP redirects before page renders.

    var paymentIntent = urlParams.get("payment_intent");
    var orderId = urlParams.get("order_id");
    var guestToken = urlParams.get("token") || "";

    if (paymentIntent && orderId) {
      // Confirm payment status with our server
      var confirmData = {
        action: "fc_stripe_confirm_payment",
        nonce: fc_stripe_params.nonce,
        order_id: orderId,
        intent_id: paymentIntent,
      };
      if (guestToken) confirmData.order_token = guestToken;

      $.ajax({
        url: fc_stripe_params.ajax_url,
        method: "POST",
        data: confirmData,
      });

      // Clean up URL (remove Stripe params)
      if (window.history && window.history.replaceState) {
        var cleanUrl = window.location.pathname + "?order_id=" + orderId;
        if (guestToken) cleanUrl += "&token=" + guestToken;
        window.history.replaceState({}, "", cleanUrl);
      }
    }

    // Also handle legacy stripe=1 redirect
    if (urlParams.get("stripe") === "1" && orderId) {
      var intentFromUrl = urlParams.get("payment_intent");
      if (intentFromUrl) {
        var legacyData = {
          action: "fc_stripe_confirm_payment",
          nonce: fc_stripe_params.nonce,
          order_id: orderId,
          intent_id: intentFromUrl,
        };
        if (guestToken) legacyData.order_token = guestToken;

        $.ajax({
          url: fc_stripe_params.ajax_url,
          method: "POST",
          data: legacyData,
        });
      }
    }
  });

  /* ── UI Helpers ─────────────────────────────────────────── */

  function showOverlay(message) {
    removeOverlay();
    var html =
      '<div class="fc-stripe-overlay" id="fc-stripe-overlay">' +
      '<div class="fc-stripe-overlay-inner">' +
      '<div class="fc-spinner"></div>' +
      "<p>" +
      escapeHtml(message) +
      "</p>" +
      "</div>" +
      "</div>";
    $("body").append(html);
  }

  function updateOverlay(message) {
    var $inner = $("#fc-stripe-overlay .fc-stripe-overlay-inner");
    if ($inner.length) {
      $inner.html(
        '<div style="font-size:2rem;color:#27ae60;margin-bottom:0.5rem;">✓</div><p>' +
          escapeHtml(message) +
          "</p>",
      );
    }
  }

  function removeOverlay() {
    $("#fc-stripe-overlay").remove();
  }

  function handleError(message) {
    removeOverlay();

    // Show in errors element
    var errEl = document.getElementById("fc-stripe-errors");
    if (errEl) {
      errEl.textContent = message;
    }

    // Also show as alert if we're not on step 3
    var $activeStep = $(".fc-checkout-step.active");
    if ($activeStep.length && $activeStep.data("step") !== 3) {
      alert(message);
    }
  }

  function escapeHtml(text) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
  }

  /* ── Retry Payment Page ────────────────────────────────── */

  $(function () {
    var $retryBtn = $("#fc-stripe-retry-btn");
    if (!$retryBtn.length) return;

    var retryOrderId = $retryBtn.data("order-id");
    var retryElements = null;
    var retryPaymentElement = null;
    var retryIntentSecret = null;
    var retryIntentId = null;
    var retryMounted = false;
    var retryBilling = {};

    // Initialize countdown timer
    $(".fc-retry-countdown, .fc-retry-countdown-inline").each(function () {
      var $el = $(this);
      var remaining = parseInt($el.data("deadline"), 10) || 0;
      var $timer = $el.find(".fc-countdown-timer");

      function updateTimer() {
        if (remaining <= 0) {
          $timer.text("0:00");
          $retryBtn
            .prop("disabled", true)
            .text(fc_stripe_params.i18n.payment_expired || "Expired");
          return;
        }
        var mins = Math.floor(remaining / 60);
        var secs = remaining % 60;
        $timer.text(mins + ":" + (secs < 10 ? "0" : "") + secs);
        remaining--;
        setTimeout(updateTimer, 1000);
      }
      updateTimer();
    });

    // Get guest token from URL for AJAX calls
    var urlParams = new URLSearchParams(window.location.search);
    var token = urlParams.get("token");

    // Create PaymentIntent for retry and mount Payment Element
    var retryMount = document.getElementById("fc-stripe-retry-element");
    if (!retryMount) return;

    retryMount.innerHTML =
      '<div class="fc-stripe-loading"><div class="fc-spinner"></div></div>';

    var retryIntentData = {
      action: "fc_stripe_retry_intent",
      nonce: fc_stripe_params.nonce,
      order_id: retryOrderId,
    };
    if (token) retryIntentData.order_token = token;

    $.ajax({
      url: fc_stripe_params.ajax_url,
      method: "POST",
      data: retryIntentData,
      success: function (res) {
        if (!res.success) {
          retryMount.innerHTML =
            '<div class="fc-stripe-element-error">' +
            escapeHtml(res.data ? res.data.message : "Error") +
            "</div>";
          $retryBtn.prop("disabled", true);
          return;
        }

        retryIntentSecret = res.data.clientSecret;
        retryIntentId = res.data.intentId;

        var appearance = {
          theme: "stripe",
          variables: {
            colorPrimary: "#d4a843",
            colorBackground: "#ffffff",
            colorText: "#1d2327",
            colorDanger: "#e74c3c",
            fontFamily:
              '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            fontSizeBase: "15px",
            borderRadius: "6px",
            spacingUnit: "4px",
          },
          rules: {
            ".Input:focus": {
              borderColor: "#d4a843",
              boxShadow: "0 0 0 3px rgba(212, 168, 67, 0.15)",
            },
          },
        };

        retryElements = stripe.elements({
          clientSecret: retryIntentSecret,
          appearance: appearance,
          locale: fc_stripe_params.locale || "auto",
        });

        retryBilling = res.data.billingDetails || {};

        retryPaymentElement = retryElements.create("payment", {
          layout: {
            type: "tabs",
            defaultCollapsed: false,
          },
          defaultValues: {
            billingDetails: retryBilling,
          },
          fields: {
            billingDetails: {
              name: "never",
              email: "never",
              phone: "never",
              address: {
                country: "never",
              },
            },
          },
        });

        retryMount.innerHTML = "";
        retryPaymentElement.mount("#fc-stripe-retry-element");
        retryMounted = true;

        retryPaymentElement.on("change", function (event) {
          var errEl = document.getElementById("fc-stripe-retry-errors");
          if (errEl) {
            errEl.textContent = event.error ? event.error.message : "";
          }
        });
      },
      error: function () {
        retryMount.innerHTML =
          '<div class="fc-stripe-element-error">' +
          escapeHtml(fc_stripe_params.i18n.generic_error) +
          "</div>";
        $retryBtn.prop("disabled", true);
      },
    });

    // Handle retry payment button click
    $retryBtn.on("click", function () {
      if (!retryMounted || !retryIntentSecret) return;

      var $btn = $(this);
      $btn.prop("disabled", true);

      showOverlay(fc_stripe_params.i18n.processing);

      // Build return URL for redirect-based methods
      var returnUrl = fc_stripe_params.return_url || window.location.origin;
      returnUrl +=
        (returnUrl.indexOf("?") !== -1 ? "&" : "?") +
        "order_id=" +
        retryOrderId +
        "&stripe=1";

      if (token) returnUrl += "&token=" + token;

      stripe
        .confirmPayment({
          elements: retryElements,
          confirmParams: {
            return_url: returnUrl,
            payment_method_data: {
              billing_details: retryBilling,
            },
          },
          redirect: "if_required",
        })
        .then(function (result) {
          if (result.error) {
            removeOverlay();
            var errEl = document.getElementById("fc-stripe-retry-errors");
            if (errEl) errEl.textContent = result.error.message;
            $btn.prop("disabled", false);
          } else if (
            result.paymentIntent &&
            result.paymentIntent.status === "succeeded"
          ) {
            updateOverlay(fc_stripe_params.i18n.payment_success);
            // Confirm with server
            var confirmData = {
              action: "fc_stripe_confirm_payment",
              nonce: fc_stripe_params.nonce,
              order_id: retryOrderId,
              intent_id: retryIntentId,
            };
            if (token) confirmData.order_token = token;
            $.ajax({
              url: fc_stripe_params.ajax_url,
              method: "POST",
              data: confirmData,
            });
            // Redirect to normal thank-you page
            var thankUrl =
              fc_stripe_params.return_url || window.location.origin;
            thankUrl +=
              (thankUrl.indexOf("?") !== -1 ? "&" : "?") +
              "order_id=" +
              retryOrderId;
            if (token) thankUrl += "&token=" + token;
            setTimeout(function () {
              window.location.href = thankUrl;
            }, 600);
          } else {
            updateOverlay(fc_stripe_params.i18n.processing);
            setTimeout(function () {
              window.location.href = returnUrl;
            }, 1000);
          }
        });
    });
  });
})(jQuery);
