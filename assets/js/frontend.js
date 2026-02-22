/**
 * Flavor Commerce — Frontend JavaScript
 */
(function ($) {
  "use strict";

  // ===== Product title tooltip =====
  (function () {
    var $tip = null;
    $(document).on(
      "mouseenter",
      ".fc-product-title-wrap[data-tooltip]",
      function () {
        var text = $(this).attr("data-tooltip");
        if (!text) return;
        if (!$tip) {
          $tip = $('<div class="fc-title-tooltip"></div>').appendTo("body");
        }
        $tip.text(text).removeClass("fc-tooltip-visible");
        var rect = this.getBoundingClientRect();
        $tip.css({
          left: rect.left + rect.width / 2 + "px",
          top: "0px",
          transform: "translateX(-50%)",
        });
        var tipH = $tip.outerHeight();
        $tip
          .css("top", rect.top - tipH - 8 + "px")
          .addClass("fc-tooltip-visible");
      },
    );
    $(document).on(
      "mouseleave",
      ".fc-product-title-wrap[data-tooltip]",
      function () {
        if ($tip) $tip.removeClass("fc-tooltip-visible");
      },
    );
  })();

  // ===== Compare button toggle =====
  $(document).on("click", ".fc-compare-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $btn = $(this);
    var productId = $btn.data("product-id");
    var onComparePage = $(".fc-compare-page").length > 0;

    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_compare_toggle",
        product_id: productId,
        nonce: fc_ajax.nonce,
      },
      success: function (res) {
        if (res.success) {
          // Toggle active state on all compare buttons for this product
          $(".fc-compare-btn[data-product-id='" + productId + "']")
            .toggleClass("active", res.data.added)
            .each(function () {
              var $span = $(this).find("span:last");
              if ($span.length) {
                $span.text(
                  res.data.added
                    ? fc_ajax.i18n.compare_in_list
                    : fc_ajax.i18n.compare_add,
                );
              }
            });
          fcShowToast(res.data.message, "success");
          fcUpdateCompareCount(res.data.count);
          // Refresh compare panel if open
          if ($(".fc-panel-compare").hasClass("fc-panel-active")) {
            fcRefreshComparePanel();
          }
          // Auto-open compare panel on add
          if (res.data.added && fc_ajax.open_compare_on_add === "1") {
            fcRefreshComparePanel(function () {
              fcOpenComparePanel();
            });
          }
          // Reload compare page after removing product
          if (onComparePage && !res.data.added) location.reload();
        } else {
          fcShowToast(
            res.data && typeof res.data === "string"
              ? res.data
              : fc_ajax.i18n.compare_error,
            "error",
          );
        }
      },
    });
  });

  // ===== Compare panel: clear all =====
  $(document).on(
    "click",
    ".fc-compare-panel-clear, .fc-compare-page-clear",
    function (e) {
      e.preventDefault();
      var isPage = $(this).hasClass("fc-compare-page-clear");
      $.ajax({
        url: fc_ajax.url,
        type: "POST",
        data: { action: "fc_compare_clear", nonce: fc_ajax.nonce },
        success: function (res) {
          if (res.success) {
            $(".fc-compare-btn").removeClass("active");
            fcUpdateCompareCount(0);
            fcRefreshComparePanel();
            fcShowToast(
              res.data.message || fc_ajax.i18n.compare_cleared,
              "success",
            );
            if (isPage) location.reload();
          }
        },
      });
    },
  );

  // ===== Compare panel: remove single item =====
  $(document).on("click", ".fc-compare-panel-remove", function (e) {
    e.preventDefault();
    var productId = $(this).data("product-id");
    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_compare_toggle",
        product_id: productId,
        nonce: fc_ajax.nonce,
      },
      success: function (res) {
        if (res.success) {
          $(".fc-compare-btn[data-product-id='" + productId + "']").removeClass(
            "active",
          );
          fcUpdateCompareCount(res.data.count);
          fcRefreshComparePanel();
        }
      },
    });
  });

  // ===== Compare count badge update =====
  function fcUpdateCompareCount(count) {
    var $badge = $(".fc-compare-count");
    if (!$badge.length) return;
    $badge.text(count);
    if (count > 0) {
      $badge.show();
      $(".fc-compare-panel-footer").show();
    } else {
      $badge.hide();
      $(".fc-compare-panel-footer").hide();
    }
    // Enable/disable compare go button (min 2 products)
    var $goBtn = $(".fc-compare-go-btn");
    if ($goBtn.length) {
      if (count >= 2) {
        $goBtn.removeClass("fc-btn-disabled");
      } else {
        $goBtn.addClass("fc-btn-disabled");
      }
    }
  }

  // ===== Open compare panel =====
  function fcOpenComparePanel() {
    // Switch panels
    $(".fc-panel").removeClass("fc-panel-active");
    $(".fc-panel-compare").addClass("fc-panel-active");
    // Highlight active tab
    $(".fc-mini-cart-tab").removeClass("fc-tab-active");
    $("#fc-mini-compare-tab").addClass("fc-tab-active");
    // Open sidebar
    $("#fc-mini-cart, #fc-mini-cart-overlay").addClass("active");
    $("body").css("overflow", "hidden");
  }

  // ===== Refresh compare panel content via AJAX =====
  function fcRefreshComparePanel(callback) {
    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_get_compare_panel",
        nonce: fc_ajax.nonce,
      },
      success: function (res) {
        if (res.success) {
          $(".fc-compare-panel-items").html(res.data.compare_html);
          fcUpdateCompareCount(res.data.count);
        }
        if (typeof callback === "function") callback();
      },
    });
  }

  // ===== Compare tab click =====
  $(document).on("click", "#fc-mini-compare-tab", function (e) {
    e.stopPropagation();
    if (
      $("#fc-mini-cart").hasClass("active") &&
      $(".fc-panel-compare").hasClass("fc-panel-active")
    ) {
      fcCloseMiniCart();
    } else {
      fcRefreshComparePanel(function () {
        fcOpenComparePanel();
      });
    }
  });

  // ===== Block compare page link when < 2 products =====
  $(document).on("click", ".fc-compare-go-btn", function (e) {
    if ($(this).hasClass("fc-btn-disabled")) {
      e.preventDefault();
      fcShowToast(fc_ajax.i18n.compare_min_products, "error");
    }
  });

  // ===== Wishlist panel: open =====
  function fcOpenWishlistPanel() {
    $(".fc-panel").removeClass("fc-panel-active");
    $(".fc-panel-wishlist").addClass("fc-panel-active");
    $(".fc-mini-cart-tab").removeClass("fc-tab-active");
    $("#fc-mini-wishlist-tab").addClass("fc-tab-active");
    $("#fc-mini-cart, #fc-mini-cart-overlay").addClass("active");
    $("body").css("overflow", "hidden");
  }

  // ===== Wishlist panel: refresh via AJAX =====
  function fcRefreshWishlistPanel(callback) {
    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: { action: "fc_get_wishlist_panel", nonce: fc_ajax.nonce },
      success: function (res) {
        if (res.success) {
          $(".fc-wishlist-panel-items").html(res.data.wishlist_html);
          fcUpdateWishlistPanelCount(res.data.count);
        }
        if (typeof callback === "function") callback();
      },
    });
  }

  // ===== Wishlist panel: update count & footer visibility =====
  function fcUpdateWishlistPanelCount(count) {
    // Header badges
    $(".fc-header-wishlist-count").each(function () {
      $(this).text(count);
      $(this).toggle(count > 0);
    });
    // Footer visibility
    if (count > 0) {
      $(".fc-wishlist-panel-footer").show();
    } else {
      $(".fc-wishlist-panel-footer").hide();
    }
  }
  window.fcRefreshWishlistPanel = fcRefreshWishlistPanel;

  // ===== Wishlist tab click =====
  $(document).on("click", "#fc-mini-wishlist-tab", function (e) {
    e.stopPropagation();
    if (
      $("#fc-mini-cart").hasClass("active") &&
      $(".fc-panel-wishlist").hasClass("fc-panel-active")
    ) {
      fcCloseMiniCart();
    } else {
      fcRefreshWishlistPanel(function () {
        fcOpenWishlistPanel();
      });
    }
  });

  // ===== Wishlist panel: remove single item =====
  $(document).on("click", ".fc-wishlist-panel-remove", function (e) {
    e.preventDefault();
    var productId = $(this).data("product-id");
    var fd = new FormData();
    fd.append("action", "fc_wishlist_toggle");
    fd.append("product_id", productId);
    fd.append("nonce", fc_ajax.nonce);
    fetch(fc_ajax.url, { method: "POST", body: fd })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res.success) {
          // Update heart buttons
          $(".fc-wishlist-btn[data-product-id='" + productId + "']")
            .removeClass("active")
            .each(function () {
              var h = $(this).find(".fc-heart");
              if (h.length) h.text("\uD83E\uDD0D");
            });
          fcUpdateWishlistPanelCount(res.data.count);
          fcRefreshWishlistPanel();
        }
      });
  });

  // ===== Wishlist panel: clear all =====
  $(document).on("click", ".fc-wishlist-panel-clear", function (e) {
    e.preventDefault();
    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: { action: "fc_wishlist_clear", nonce: fc_ajax.nonce },
      success: function (res) {
        if (res.success) {
          $(".fc-wishlist-btn")
            .removeClass("active")
            .each(function () {
              var h = $(this).find(".fc-heart");
              if (h.length) h.text("\uD83E\uDD0D");
            });
          fcUpdateWishlistPanelCount(0);
          fcRefreshWishlistPanel();
          fcShowToast(res.data.message, "success");
        }
      },
    });
  });

  // ===== Add to Cart (grid — simple products) =====
  $(document).on("click", ".fc-add-to-cart", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var productId = $btn.data("product-id");
    var originalText = $btn.text();

    $btn.prop("disabled", true).text("...");

    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_add_to_cart",
        nonce: fc_ajax.nonce,
        product_id: productId,
        quantity: 1,
      },
      success: function (res) {
        if (res.success) {
          fcShowToast(res.data.message, "success");
          fcUpdateCartCount(res.data.cart_count);
          fcUpdateMiniCart(res.data);
          var onCartPage = $(".fc-cart-page").length > 0;
          if (!onCartPage && fc_ajax.open_minicart_on_add === "1")
            fcOpenMiniCart();
          if (onCartPage) {
            location.reload();
            return;
          }
          $btn.text(fc_ajax.i18n.added_to_cart);
          setTimeout(function () {
            $btn.text(originalText).prop("disabled", false);
          }, 1500);
        } else {
          fcShowToast(res.data.message, "error");
          $btn.text(originalText).prop("disabled", false);
        }
      },
      error: function () {
        fcShowToast(fc_ajax.i18n.generic_error, "error");
        $btn.text(originalText).prop("disabled", false);
      },
    });
  });

  // ===== Add to Cart (single product page) =====
  $(document).on("click", ".fc-add-to-cart-single", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var productId = $btn.data("product-id");
    var $qtyInput = $(".fc-qty-input-single");
    var max = parseInt($qtyInput.attr("max")) || 99;
    var quantity = parseInt($qtyInput.val()) || 1;
    if (quantity > max) {
      quantity = max;
      $qtyInput.val(max);
      if (max < 99) {
        fcShowToast(fcStockLimitMsg(max), "error");
      }
    }
    var originalText = $btn.text();

    // Variant support — attribute tiles
    var variantId = "";
    var $attrWrap = $(".fc-variant-attributes");
    if ($attrWrap.length) {
      variantId = $attrWrap.find(".fc-selected-variant-id").val();
      if (!variantId && variantId !== "0") {
        fcShowToast(fc_ajax.i18n.select_all_attributes, "error");
        return;
      }
    }

    $btn.prop("disabled", true).text("...");

    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_add_to_cart",
        nonce: fc_ajax.nonce,
        product_id: productId,
        quantity: quantity,
        variant_id: variantId,
      },
      success: function (res) {
        if (res.success) {
          fcShowToast(res.data.message, "success");
          fcUpdateCartCount(res.data.cart_count);
          fcUpdateMiniCart(res.data);
          var onCartPage = $(".fc-cart-page").length > 0;
          if (!onCartPage && fc_ajax.open_minicart_on_add === "1")
            fcOpenMiniCart();
          if (onCartPage) {
            location.reload();
            return;
          }
          $btn.text(fc_ajax.i18n.added_to_cart);
          setTimeout(function () {
            $btn.text(originalText).prop("disabled", false);
          }, 1500);
        } else {
          fcShowToast(res.data.message, "error");
          $btn.text(originalText).prop("disabled", false);
        }
      },
      error: function () {
        fcShowToast(fc_ajax.i18n.generic_error, "error");
        $btn.text(originalText).prop("disabled", false);
      },
    });
  });

  // ===== Quantity +/- =====
  function fcStockLimitMsg(max) {
    var unit = $(".fc-stock-info").data("unit") || "";
    var msg = fc_ajax.i18n.stock_limit.replace("%d", max).replace("%s", unit);
    return msg;
  }

  $(document).on("click", ".fc-qty-plus", function () {
    var $input = $(this).siblings(".fc-qty-input-single");
    var max = parseInt($input.attr("max")) || 99;
    var val = parseInt($input.val()) || 1;
    if (val < max) {
      $input.val(val + 1);
    } else if (max < 99) {
      fcShowToast(fcStockLimitMsg(max), "error");
    }
  });

  $(document).on("click", ".fc-qty-minus", function () {
    var $input = $(this).siblings(".fc-qty-input-single");
    var val = parseInt($input.val()) || 1;
    if (val > 1) $input.val(val - 1);
  });

  // Validate manually typed quantity against max
  $(document).on("change", ".fc-qty-input-single", function () {
    var $input = $(this);
    var max = parseInt($input.attr("max")) || 99;
    var val = parseInt($input.val()) || 1;
    if (val < 1) val = 1;
    if (val > max) {
      val = max;
      if (max < 99) {
        fcShowToast(fcStockLimitMsg(max), "error");
      }
    }
    $input.val(val);
  });

  // ===== Cart: Remove =====
  $(document).on("click", ".fc-remove-item", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var productId = $btn.data("product-id");

    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_remove_from_cart",
        nonce: fc_ajax.nonce,
        product_id: productId,
      },
      success: function (res) {
        if (res.success) {
          $btn.closest(".fc-cart-row").fadeOut(300, function () {
            $(this).remove();
            if ($(".fc-cart-row").length === 0) {
              location.reload();
              return;
            }
            // Reload to update cross-sell section
            location.reload();
          });
          $(".fc-total-amount").text(res.data.cart_total);
          fcUpdateCartCount(res.data.cart_count);
        }
      },
      error: function () {
        fcShowToast(fc_ajax.i18n.generic_error, "error");
      },
    });
  });

  // ===== Cart: Update Quantity =====
  var qtyTimer;
  $(document).on("change", ".fc-qty-input", function () {
    var $input = $(this);
    var productId = $input.data("product-id");
    var quantity = parseInt($input.val()) || 1;

    clearTimeout(qtyTimer);
    qtyTimer = setTimeout(function () {
      $.ajax({
        url: fc_ajax.url,
        type: "POST",
        data: {
          action: "fc_update_cart",
          nonce: fc_ajax.nonce,
          product_id: productId,
          quantity: quantity,
        },
        success: function (res) {
          if (res.success) {
            $input
              .closest(".fc-cart-row")
              .find(".fc-cart-line-total")
              .text(res.data.line_total);
            $(".fc-total-amount").text(res.data.cart_total);
            fcUpdateCartCount(res.data.cart_count);
          }
        },
        error: function () {
          fcShowToast(fc_ajax.i18n.generic_error, "error");
        },
      });
    }, 400);
  });

  // ===== Gallery =====
  var fcGalleryIndex = 0;
  var fcThumbOffset = 0;
  var FC_VISIBLE_THUMBS = 6;
  var fcOriginalThumbs = []; // Store original gallery order

  // Save original gallery on page load
  (function () {
    $(".fc-product-gallery .fc-thumb").each(function () {
      fcOriginalThumbs.push({
        full: $(this).data("full"),
        thumb: $(this).find("img").attr("src"),
        index: parseInt($(this).data("index"), 10),
      });
    });
  })();

  function fcGetGalleryTotal() {
    return $(".fc-product-gallery .fc-thumb").length;
  }

  function fcGalleryGoTo(idx) {
    var $thumbs = $(".fc-product-gallery .fc-thumb");
    var total = $thumbs.length;
    if (total === 0) return;
    if (idx < 0) idx = total - 1;
    if (idx >= total) idx = 0;
    fcGalleryIndex = idx;

    var fullUrl = $thumbs.eq(idx).data("full");
    if (fullUrl) {
      $(".fc-main-image img").attr("src", fullUrl);
    }
    $thumbs.removeClass("active");
    $thumbs.eq(idx).addClass("active");

    // Auto-scroll thumbs to keep active visible
    if (total > FC_VISIBLE_THUMBS) {
      if (idx < fcThumbOffset) {
        fcThumbOffset = idx;
      } else if (idx >= fcThumbOffset + FC_VISIBLE_THUMBS) {
        fcThumbOffset = idx - FC_VISIBLE_THUMBS + 1;
      }
      fcUpdateThumbsPosition();
    }
  }

  function fcUpdateThumbsPosition() {
    var $track = $(".fc-thumbs-track");
    if (!$track.length) return;
    // thumb width 70px + gap 8px
    var step = 78;
    $track.css("transform", "translateX(-" + fcThumbOffset * step + "px)");
  }

  // Thumb click
  $(document).on("click", ".fc-thumb", function () {
    var idx = parseInt($(this).data("index"), 10);
    fcGalleryGoTo(idx);
  });

  // Main image arrows
  $(document).on("click", ".fc-main-prev", function () {
    fcGalleryGoTo(fcGalleryIndex - 1);
  });
  $(document).on("click", ".fc-main-next", function () {
    fcGalleryGoTo(fcGalleryIndex + 1);
  });

  // Thumb strip arrows
  $(document).on("click", ".fc-thumbs-prev", function () {
    var total = fcGetGalleryTotal();
    if (fcThumbOffset > 0) {
      fcThumbOffset--;
      fcUpdateThumbsPosition();
    }
  });
  $(document).on("click", ".fc-thumbs-next", function () {
    var total = fcGetGalleryTotal();
    var maxOffset = Math.max(0, total - FC_VISIBLE_THUMBS);
    if (fcThumbOffset < maxOffset) {
      fcThumbOffset++;
      fcUpdateThumbsPosition();
    }
  });

  // Reorder gallery: variant images first, then rest
  function fcReorderGallery(variantImages) {
    if (
      !variantImages ||
      variantImages.length === 0 ||
      fcOriginalThumbs.length === 0
    )
      return;

    var variantFullUrls = variantImages.map(function (vi) {
      return vi.full;
    });

    // Build new order: variant images first, then others
    var newOrder = [];
    // Add variant images in order
    variantImages.forEach(function (vi) {
      newOrder.push({ full: vi.full, thumb: vi.thumb });
    });
    // Add remaining original images not in variant
    fcOriginalThumbs.forEach(function (orig) {
      var isVariant = false;
      for (var i = 0; i < variantFullUrls.length; i++) {
        if (orig.full === variantFullUrls[i]) {
          isVariant = true;
          break;
        }
      }
      if (!isVariant) {
        newOrder.push({ full: orig.full, thumb: orig.thumb });
      }
    });

    fcRebuildGallery(newOrder);
  }

  function fcResetGallery() {
    if (fcOriginalThumbs.length === 0) return;
    var order = fcOriginalThumbs.map(function (o) {
      return { full: o.full, thumb: o.thumb };
    });
    fcRebuildGallery(order);
  }

  function fcRebuildGallery(imagesArr) {
    var $track = $(".fc-thumbs-track");
    if (!$track.length) return;

    $track.empty();
    imagesArr.forEach(function (img, idx) {
      $track.append(
        '<div class="fc-thumb' +
          (idx === 0 ? " active" : "") +
          '" data-full="' +
          esc(img.full) +
          '" data-index="' +
          idx +
          '">' +
          '<img src="' +
          esc(img.thumb) +
          '" alt="">' +
          "</div>",
      );
    });

    // Update main image
    if (imagesArr.length > 0) {
      $(".fc-main-image img").attr("src", imagesArr[0].full);
    }

    // Reset navigation state
    fcGalleryIndex = 0;
    fcThumbOffset = 0;
    fcUpdateThumbsPosition();

    // Update nav arrows visibility
    var total = imagesArr.length;
    var $wrapper = $(".fc-thumbs-wrapper");
    if (total > FC_VISIBLE_THUMBS) {
      $wrapper.addClass("has-nav");
      if (!$wrapper.find(".fc-thumbs-prev").length) {
        $wrapper.prepend(
          '<button type="button" class="fc-thumbs-nav fc-thumbs-prev" aria-label="' +
            fc_ajax.i18n.scroll_label +
            '">&#8249;</button>',
        );
        $wrapper.append(
          '<button type="button" class="fc-thumbs-nav fc-thumbs-next" aria-label="' +
            fc_ajax.i18n.scroll_label +
            '">&#8250;</button>',
        );
      }
    } else {
      $wrapper.removeClass("has-nav");
      $wrapper.find(".fc-thumbs-nav").remove();
    }

    // Show/hide main nav
    if (total > 1) {
      $(".fc-main-nav").show();
    } else {
      $(".fc-main-nav").hide();
    }
  }

  // ===== Lightbox (tablet+ only, min 768px) =====
  var fcLightboxIndex = 0;

  function fcIsLightboxEnabled() {
    return window.innerWidth >= 768;
  }

  function fcLightboxGetImages() {
    var imgs = [];
    $(".fc-product-gallery .fc-thumb").each(function () {
      imgs.push($(this).data("full"));
    });
    if (imgs.length === 0) {
      var mainSrc = $(".fc-main-image img").attr("src");
      if (mainSrc) imgs.push(mainSrc);
    }
    return imgs;
  }

  function fcOpenLightbox(idx) {
    var imgs = fcLightboxGetImages();
    if (imgs.length === 0) return;
    if (idx < 0 || idx >= imgs.length) idx = 0;
    fcLightboxIndex = idx;
    $("#fc_lightbox").find(".fc-lightbox-img").attr("src", imgs[idx]);
    $("#fc_lightbox").addClass("active");
    $("body").css("overflow", "hidden");

    // Show/hide nav
    if (imgs.length <= 1) {
      $(".fc-lightbox-nav").hide();
    } else {
      $(".fc-lightbox-nav").show();
    }
  }

  function fcCloseLightbox() {
    $("#fc_lightbox").removeClass("active");
    $("body").css("overflow", "");
  }

  function fcLightboxGo(dir) {
    var imgs = fcLightboxGetImages();
    if (imgs.length === 0) return;
    fcLightboxIndex += dir;
    if (fcLightboxIndex < 0) fcLightboxIndex = imgs.length - 1;
    if (fcLightboxIndex >= imgs.length) fcLightboxIndex = 0;
    $("#fc_lightbox")
      .find(".fc-lightbox-img")
      .attr("src", imgs[fcLightboxIndex]);
  }

  // Open on main image click
  $(document).on("click", ".fc-main-image img", function (e) {
    if (!fcIsLightboxEnabled()) return;
    e.preventDefault();
    fcOpenLightbox(fcGalleryIndex);
  });

  // Close
  $(document).on("click", ".fc-lightbox-close", fcCloseLightbox);
  $(document).on("click", ".fc-lightbox-backdrop", fcCloseLightbox);

  // Navigate
  $(document).on("click", ".fc-lightbox-prev", function (e) {
    e.stopPropagation();
    fcLightboxGo(-1);
  });
  $(document).on("click", ".fc-lightbox-next", function (e) {
    e.stopPropagation();
    fcLightboxGo(1);
  });

  // Keyboard: Esc, Left, Right
  $(document).on("keydown", function (e) {
    if (!$("#fc_lightbox").hasClass("active")) return;
    if (e.key === "Escape") fcCloseLightbox();
    if (e.key === "ArrowLeft") fcLightboxGo(-1);
    if (e.key === "ArrowRight") fcLightboxGo(1);
  });

  // ===== Toast =====
  function fcEscHtmlFront(s) {
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }

  function fcShowToast(message, type, trustedHtml) {
    var $existing = $(".fc-toast");
    if ($existing.length) $existing.remove();

    var $toast = $('<div class="fc-toast"></div>').addClass(type);
    if (trustedHtml) {
      $toast.html(message);
    } else {
      $toast.html(fcEscHtmlFront(message));
    }
    $("body").append($toast);

    setTimeout(function () {
      $toast.addClass("show");
    }, 50);

    setTimeout(function () {
      $toast.removeClass("show");
      setTimeout(function () {
        $toast.remove();
      }, 300);
    }, 3000);
  }

  // Expose globally for inline scripts (wishlist, compare, etc.)
  window.fcShowToast = fcShowToast;
  window.fcOpenWishlistPanel = fcOpenWishlistPanel;

  // ===== Update Cart Count =====
  function fcUpdateCartCount(count) {
    var $badge = $(".fc-cart-count");
    if ($badge.length) {
      $badge.text(count);
      if (count > 0) {
        $badge.show();
      } else {
        $badge.hide();
      }
    }
  }

  // ===================================================================
  //  Attribute Tile Variant Selector — Frontend (Single Product Page)
  // ===================================================================

  // Pre-select attributes from URL params (fc_attr_Kolor=red etc.)
  (function () {
    var params = new URLSearchParams(window.location.search);
    var $wrap = $(".fc-variant-attributes");
    if (!$wrap.length) return;

    var found = false;
    $wrap.find(".fc-attr-selector").each(function () {
      var attrName = $(this).data("attr-name");
      var paramVal = params.get("fc_attr_" + attrName);
      if (paramVal) {
        var $tile = $(this)
          .find(".fc-attr-tile")
          .filter(function () {
            return (
              String($(this).data("value")).toLowerCase() ===
              paramVal.toLowerCase()
            );
          });
        if ($tile.length) {
          $(this).find(".fc-attr-tile").removeClass("active");
          $tile.addClass("active");
          found = true;
        }
      }
    });
    if (found) {
      // Trigger matching after a small delay to ensure data is loaded
      setTimeout(function () {
        fcFilterAvailableTiles();
        fcMatchVariant();
      }, 100);
    }
  })();

  // Load variant data
  var fcVariantsData = [];
  try {
    var $jsonScript = $(".fc-variants-data");
    if ($jsonScript.length) {
      fcVariantsData = JSON.parse($jsonScript.text());
    }
  } catch (e) {
    fcVariantsData = [];
  }

  // Initial filter on page load — disable tiles leading only to OOS variants
  if (fcVariantsData.length) {
    fcFilterAvailableTiles();
  }

  // Click on attribute tile
  $(document).on("click", ".fc-attr-tile:not(.fc-tile-disabled)", function () {
    var $tile = $(this);
    var $selector = $tile.closest(".fc-attr-selector");

    // Toggle active on this tile group
    $selector.find(".fc-attr-tile").removeClass("active");
    $tile.addClass("active");

    // Filter available tiles then match
    fcFilterAvailableTiles();
    fcMatchVariant();
  });

  // Cache unit text for variant price updates
  var fcUnitHtml = "";
  (function () {
    var $u = $(".fc-price-large > .fc-price-unit");
    if ($u.length) {
      fcUnitHtml = ' <span class="fc-price-unit">' + $u.text() + "</span>";
    }
  })();

  function fcMatchVariant() {
    var $attrWrap = $(".fc-variant-attributes");
    if (!$attrWrap.length) return;

    var $addBtn = $(".fc-add-to-cart-single");
    var selections = {};
    var allSelected = true;

    $attrWrap.find(".fc-attr-selector").each(function () {
      var attrName = $(this).data("attr-name");
      var $active = $(this).find(".fc-attr-tile.active");
      if ($active.length) {
        selections[attrName] = $active.data("value");
      } else {
        allSelected = false;
      }
    });

    if (!allSelected) {
      $attrWrap.find(".fc-selected-variant-id").val("");
      $addBtn.prop("disabled", true).text(fc_ajax.i18n.select_variants).show();
      $(".fc-qty-wrapper").show();
      $(".fc-notify-variant-btn").hide();
      // Show price range
      $(".fc-price-large > span:first").show();
      $(".fc-price-large > .fc-price-unit").show();
      $(".fc-price-selected").hide();
      fcResetGallery();
      return;
    }

    // Find matching variant
    var matched = null;
    for (var i = 0; i < fcVariantsData.length; i++) {
      var v = fcVariantsData[i];
      var attrVals = v.attribute_values || {};
      var isMatch = true;
      for (var key in selections) {
        if (
          String(attrVals[key]).toLowerCase() !==
          String(selections[key]).toLowerCase()
        ) {
          isMatch = false;
          break;
        }
      }
      if (isMatch) {
        matched = v;
        break;
      }
    }

    if (matched) {
      $attrWrap
        .find(".fc-selected-variant-id")
        .val(matched.id || matched.index);

      var price = parseFloat(matched.price) || 0;
      var salePrice = parseFloat(matched.sale_price) || 0;
      var stock = matched.stock;
      var symbol = fc_ajax.currency;
      var pos = fc_ajax.currency_pos || "after";

      function fcFormatPrice(val) {
        var f = parseFloat(val).toFixed(2);
        var parts = f.split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, " ");
        var formatted = parts[0] + "," + parts[1];
        if (pos === "before") return symbol + " " + formatted;
        return formatted + " " + symbol;
      }

      var displayPrice = "";
      if (salePrice > 0 && salePrice < price) {
        var pct = matched.sale_percent
          ? parseFloat(matched.sale_percent)
          : Math.round(((price - salePrice) / price) * 100);
        displayPrice =
          "<del>" +
          fcFormatPrice(price) +
          "</del> <ins>" +
          fcFormatPrice(salePrice) +
          "</ins>";
      } else {
        displayPrice = fcFormatPrice(price);
      }

      $(".fc-price-large > span:first").hide();
      $(".fc-price-large > .fc-price-unit").hide();
      $(".fc-price-selected")
        .html(displayPrice + fcUnitHtml)
        .show();

      // Update gallery with variant images first, main_image as #1
      if (matched.images && matched.images.length > 0) {
        var orderedImages = matched.images.slice();
        if (matched.main_image) {
          var mainId = parseInt(matched.main_image);
          var mainIdx = -1;
          for (var mi = 0; mi < orderedImages.length; mi++) {
            if (parseInt(orderedImages[mi].id) === mainId) {
              mainIdx = mi;
              break;
            }
          }
          if (mainIdx > 0) {
            var mainImg = orderedImages.splice(mainIdx, 1)[0];
            orderedImages.unshift(mainImg);
          }
        }
        fcReorderGallery(orderedImages);
      } else {
        fcResetGallery();
      }

      // Stock
      var stockInt =
        stock !== "" && stock !== undefined && stock !== null
          ? parseInt(stock)
          : -1;
      if (stockInt >= 0) {
        $(".fc-qty-input-single").attr("max", stockInt);
      } else {
        $(".fc-qty-input-single").attr("max", 99);
      }

      // Update stock badge for selected variant
      var $stockInfo = $(".fc-stock-info");
      if ($stockInfo.length) {
        if (stockInt === 0) {
          $stockInfo.html(
            '<span class="fc-stock-badge out">' +
              fc_ajax.i18n.out_of_stock_badge +
              "</span>",
          );
        } else if (stockInt > 0) {
          var unitText = $stockInfo.data("unit") || "";
          $stockInfo.html(
            '<span class="fc-stock-badge in">' +
              fc_ajax.i18n.in_stock_badge +
              '</span> <span class="fc-stock-qty">(' +
              stockInt +
              (unitText ? " " + unitText : "") +
              ")</span>",
          );
        } else {
          $stockInfo.html(
            '<span class="fc-stock-badge in">' +
              fc_ajax.i18n.in_stock_badge +
              "</span>",
          );
        }
      }

      // Disable add-to-cart when variant is out of stock
      var $notifyBtn = $(".fc-notify-variant-btn");
      var $qtyWrapper = $(".fc-qty-wrapper");
      if (stockInt === 0) {
        $addBtn.prop("disabled", true).text(fc_ajax.i18n.unavailable).hide();
        $qtyWrapper.hide();
        if ($notifyBtn.length) {
          $notifyBtn
            .attr("data-product-id", $attrWrap.data("product-id"))
            .show();
        }
      } else {
        $addBtn.prop("disabled", false).text(fc_ajax.i18n.add_to_cart).show();
        $qtyWrapper.show();
        if ($notifyBtn.length) $notifyBtn.hide();
      }
    } else {
      // Should not normally happen with tile filtering, but just in case
      $attrWrap.find(".fc-selected-variant-id").val("");
      $addBtn.prop("disabled", true).text(fc_ajax.i18n.unavailable).show();
      $(".fc-qty-wrapper").show();
      $(".fc-notify-variant-btn").hide();
      $(".fc-price-large > span:first").show();
      $(".fc-price-large > .fc-price-unit").show();
      $(".fc-price-selected").hide();
      fcResetGallery();
    }
  }

  /**
   * Filter available attribute tiles on single product page.
   * For each attribute group, disable tiles that lead to no valid combination
   * given the current selections in other groups.
   */
  function fcFilterAvailableTiles() {
    var $attrWrap = $(".fc-variant-attributes");
    if (!$attrWrap.length || !fcVariantsData.length) return;

    // Gather current selections
    var selections = {};
    $attrWrap.find(".fc-attr-selector").each(function () {
      var attrName = $(this).data("attr-name");
      var $active = $(this).find(".fc-attr-tile.active");
      if ($active.length) {
        selections[attrName] = $active.data("value");
      }
    });

    // For each attribute group, check which values are reachable
    $attrWrap.find(".fc-attr-selector").each(function () {
      var currentAttr = $(this).data("attr-name");

      $(this)
        .find(".fc-attr-tile")
        .each(function () {
          var tileValue = String($(this).data("value")).toLowerCase();

          // Build a test selection: current selections + this tile's value
          var testSel = {};
          for (var k in selections) {
            if (k !== currentAttr) testSel[k] = selections[k];
          }
          testSel[currentAttr] = $(this).data("value");

          // Check if any variant matches (in-stock or OOS)
          var hasAnyMatch = false;
          var hasInStockMatch = false;
          for (var i = 0; i < fcVariantsData.length; i++) {
            var v = fcVariantsData[i];
            var attrVals = v.attribute_values || {};
            var ok = true;
            for (var key in testSel) {
              if (
                String(attrVals[key]).toLowerCase() !==
                String(testSel[key]).toLowerCase()
              ) {
                ok = false;
                break;
              }
            }
            if (ok) {
              hasAnyMatch = true;
              // Check if this variant is in stock
              if (
                v.stock === undefined ||
                v.stock === "" ||
                parseInt(v.stock) > 0
              ) {
                hasInStockMatch = true;
                break;
              }
            }
          }

          if (hasInStockMatch) {
            $(this).removeClass("fc-tile-disabled fc-tile-oos");
          } else if (hasAnyMatch) {
            // Variant exists but all OOS — clickable but marked as OOS
            $(this).removeClass("fc-tile-disabled").addClass("fc-tile-oos");
          } else {
            // No variant matches at all — truly disabled
            $(this).addClass("fc-tile-disabled").removeClass("fc-tile-oos");
            if ($(this).hasClass("active")) {
              $(this).removeClass("active");
              delete selections[currentAttr];
            }
          }
        });
    });
  }

  // ===== Mini Cart (Popup Sidebar) =====

  // ===== Mobile Sidebar Filters =====
  (function () {
    var $shop = $(".fc-shop-has-sidebar");
    if (!$shop.length) return;

    var $bottomBar = $shop.find(".fc-mobile-bottom-bar");
    if (!$bottomBar.length) $bottomBar = $(".fc-mobile-bottom-bar");
    var $btn = $bottomBar.find(".fc-mobile-filters-btn");
    var $overlay = $shop.find(".fc-mobile-sidebar-overlay");
    var $close = $shop.find(".fc-mobile-sidebar-close");

    // Kopiuj aktywne filtry (z .fc-shop-content) do bottom bara
    var $barFilters = $bottomBar.find(".fc-mobile-bottom-bar-filters");
    var $activeFilters = $shop.find(".fc-active-filters .fc-active-filter-tag");
    if ($barFilters.length && $activeFilters.length) {
      $activeFilters.each(function () {
        $barFilters.append($(this).clone());
      });
    }

    // Dynamiczny padding-bottom na body dopasowany do wysokości bottom bara
    function syncBottomPadding() {
      if ($bottomBar.is(":visible")) {
        document.body.style.paddingBottom = $bottomBar.outerHeight() + "px";
      } else {
        document.body.style.paddingBottom = "";
      }
    }
    syncBottomPadding();
    $(window).on("resize", syncBottomPadding);

    // Dynamiczne kolumny siatki na podstawie CSS variables
    function syncGridColumns() {
      var grid = document.querySelector(".fc-products-grid");
      if (!grid) return;
      var styles = getComputedStyle(document.documentElement);
      var maxCols = parseInt(styles.getPropertyValue("--fc-grid-columns")) || 3;
      var minW =
        parseInt(styles.getPropertyValue("--fc-card-min-width")) || 200;
      var avail = grid.clientWidth;
      var gap = parseFloat(getComputedStyle(grid).columnGap) || 24;
      var fit = maxCols;
      while (fit > 1 && (avail - gap * (fit - 1)) / fit < minW) fit--;
      grid.style.gridTemplateColumns = "repeat(" + fit + ", 1fr)";
    }
    syncGridColumns();
    $(window).on("resize", syncGridColumns);

    function getCurrentMode() {
      var w = window.innerWidth;
      if (w <= 767) return $shop.attr("data-phone-sidebar") || "bottom_sheet";
      if (w <= 1024) return $shop.attr("data-tablet-sidebar") || "offcanvas";
      return "";
    }

    function openSidebar() {
      var mode = getCurrentMode();
      if (!mode || mode === "none") return;
      $shop.addClass("fc-mobile-sidebar-open");
      $btn.attr("aria-expanded", "true");
      if (mode !== "dropdown") {
        $("body").css("overflow", "hidden");
      }
    }

    function closeSidebar() {
      $shop.removeClass("fc-mobile-sidebar-open");
      $btn.attr("aria-expanded", "false");
      $("body").css("overflow", "");
    }

    $btn.on("click", function () {
      if ($shop.hasClass("fc-mobile-sidebar-open")) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    $overlay.on("click", function () {
      closeSidebar();
    });

    $close.on("click", function () {
      closeSidebar();
    });

    // Zamknij przez Escape
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $shop.hasClass("fc-mobile-sidebar-open")) {
        closeSidebar();
      }
    });

    // Zamknij sidebar przy przejściu na desktop
    $(window).on("resize", function () {
      if (
        window.innerWidth > 1024 &&
        $shop.hasClass("fc-mobile-sidebar-open")
      ) {
        closeSidebar();
      }
    });

    // ── Multi-select filtrów na mobile ──
    var $sidebar = $shop.find(".fc-shop-sidebar");
    var $pending = $sidebar.find(".fc-sidebar-pending-filters");
    var $footer = $sidebar.find(".fc-sidebar-footer");
    var $applyBtn = $sidebar.find(".fc-sidebar-apply");
    var pendingParams = {};

    // Parametry z URL strony przy załadowaniu (oryginalne)
    var pageParams = {};
    var urlParams = new URLSearchParams(window.location.search);
    urlParams.forEach(function (v, k) {
      if (k.indexOf("fc_") === 0 && k !== "fc_sort" && v) {
        pageParams[k] = v;
        pendingParams[k] = v;
      }
    });

    function isMobile() {
      var mode = getCurrentMode();
      return mode && mode !== "none";
    }

    function renderPending() {
      var keys = Object.keys(pendingParams);
      if (!keys.length) {
        $pending.empty();
        $footer.removeClass("has-pending");
        return;
      }
      var html = "";
      var hasPrice = false;
      keys.forEach(function (k) {
        var val = pendingParams[k];
        var prefix = k;
        if (k === "fc_cat") prefix = fc_ajax.i18n.filter_category;
        else if (k === "fc_brand") prefix = fc_ajax.i18n.filter_brand;
        else if (k === "fc_min_rating") {
          prefix = fc_ajax.i18n.filter_rating;
          val = val + "+ ★";
        } else if (k === "fc_availability") {
          prefix = fc_ajax.i18n.filter_availability;
          val = val === "instock" ? fc_ajax.i18n.filter_instock : val;
        } else if (k === "fc_search") prefix = fc_ajax.i18n.filter_search;
        else if (k === "fc_min_price" || k === "fc_max_price") {
          hasPrice = true;
          return;
        } else if (k.indexOf("fc_attr_") === 0) {
          prefix = k.substring(8).replace(/-/g, " ");
          prefix = prefix.charAt(0).toUpperCase() + prefix.slice(1);
        }
        html +=
          '<span class="fc-pending-tag" data-param="' +
          k +
          '">' +
          prefix +
          ": " +
          val +
          '<button type="button" class="fc-pending-tag-x">&times;</button>' +
          "</span>";
      });
      if (hasPrice) {
        var pl = fc_ajax.i18n.filter_price;
        if (pendingParams["fc_min_price"] && pendingParams["fc_max_price"])
          pl +=
            pendingParams["fc_min_price"] +
            " – " +
            pendingParams["fc_max_price"];
        else if (pendingParams["fc_min_price"])
          pl += fc_ajax.i18n.price_from + pendingParams["fc_min_price"];
        else pl += fc_ajax.i18n.price_to + pendingParams["fc_max_price"];
        html +=
          '<span class="fc-pending-tag" data-param="fc_min_price,fc_max_price">' +
          pl +
          '<button type="button" class="fc-pending-tag-x">&times;</button></span>';
      }
      $pending.html(html);
      $footer.addClass("has-pending");
    }

    // Wykryj jaki pojedynczy filtr link dodaje lub usuwa
    // porównując URL linka z parametrami strony (pageParams)
    function detectChange(linkHref) {
      var linkUrl;
      try {
        linkUrl = new URL(linkHref, window.location.origin);
      } catch (e) {
        return null;
      }
      var linkP = {};
      linkUrl.searchParams.forEach(function (v, k) {
        if (k.indexOf("fc_") === 0 && k !== "fc_sort") linkP[k] = v;
      });

      // Parametr dodany lub zmieniony
      for (var k in linkP) {
        if (!(k in pageParams) || pageParams[k] !== linkP[k]) {
          return { action: "set", key: k, value: linkP[k] };
        }
      }
      // Parametr usunięty
      for (var k in pageParams) {
        if (!(k in linkP)) {
          return { action: "remove", key: k };
        }
      }

      // Nie znaleziono różnicy z pageParams, ale sprawdź vs pendingParams
      // (np. już coś wybraliśmy lokalnie, teraz klikamy "Wszystkie")
      for (var k in pendingParams) {
        if (!(k in linkP)) {
          return { action: "remove", key: k };
        }
      }
      for (var k in linkP) {
        if (!(k in pendingParams) || pendingParams[k] !== linkP[k]) {
          return { action: "set", key: k, value: linkP[k] };
        }
      }

      return null;
    }

    // Przechwycenie kliknięć na linki filtrów wewnątrz sidebara (mobile)
    $sidebar.on("click", "a[href]", function (e) {
      if (!isMobile()) return;
      var $link = $(this);
      var href = $link.attr("href");
      if (!href || href === "#") return;
      // Nie przechwytuj linków footer (reset)
      if ($link.closest(".fc-sidebar-footer").length) return;

      e.preventDefault();
      e.stopPropagation();

      var change = detectChange(href);
      if (change) {
        if (change.action === "set") {
          pendingParams[change.key] = change.value;
        } else {
          delete pendingParams[change.key];
        }
      }

      // Wizualne zaznaczenie klikniętego elementu
      var $container = $link.closest(
        ".fc-sidebar-list, .fc-tag-cloud, .fc-brand-logos, .fc-attr-tiles, .fc-attr-pills, .fc-attr-circles, .fc-rating-list, .fc-attr-dropdown-list",
      );
      if ($container.length) {
        $container.find("a").removeClass("active");
        $link.addClass("active");
      }

      renderPending();
    });

    // Przycisk "Filtruj" — nawiguj do zbudowanego URL
    $applyBtn.on("click", function () {
      var base = window.location.pathname;
      var sort = urlParams.get("fc_sort");
      var finalParams = $.extend({}, pendingParams);
      if (sort) finalParams["fc_sort"] = sort;
      var qs = $.param(finalParams);
      window.location.href = base + (qs ? "?" + qs : "");
    });

    // Usuwanie tagu z paska pending
    $pending.on("click", ".fc-pending-tag-x", function () {
      var paramStr = $(this).closest(".fc-pending-tag").data("param");
      var keys = String(paramStr).split(",");
      keys.forEach(function (k) {
        delete pendingParams[k];
      });
      renderPending();
    });

    // Przechwycenie formularzy (cena, szukaj) wewnątrz sidebara na mobile
    $sidebar.on("submit", "form", function (e) {
      if (!isMobile()) return;
      e.preventDefault();
      var formData = $(this).serializeArray();
      formData.forEach(function (item) {
        if (item.name.indexOf("fc_") === 0 && item.value) {
          pendingParams[item.name] = item.value;
        }
      });
      renderPending();
    });

    renderPending();
  })();

  // ===== Collapsible sidebar categories =====
  $(document).on("click", ".fc-cat-toggle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $li = $(this).closest("li");
    $li.toggleClass("fc-cat-open");
    $li.children(".fc-sidebar-sublist").slideToggle(200);
  });

  function fcOpenMiniCart() {
    // Switch to cart panel
    $(".fc-panel").removeClass("fc-panel-active");
    $(".fc-panel-cart").addClass("fc-panel-active");
    $(".fc-mini-cart-tab").removeClass("fc-tab-active");
    $("#fc-mini-cart-tab").addClass("fc-tab-active");
    // Open sidebar
    $("#fc-mini-cart, #fc-mini-cart-overlay").addClass("active");
    $("body").css("overflow", "hidden");
  }

  function fcCloseMiniCart() {
    $("#fc-mini-cart, #fc-mini-cart-overlay").removeClass("active");
    $("body").css("overflow", "");
    $(".fc-mini-cart-tab").removeClass("fc-tab-active");
  }

  function fcUpdateMiniCart(data) {
    if (data.mini_cart) {
      $("#fc-mini-cart .fc-mini-cart-items").html(data.mini_cart);
    }
    if (data.cart_total) {
      $(".fc-mini-cart-total-value").html(data.cart_total);
    }
    if (typeof data.cart_count !== "undefined") {
      fcUpdateCartCount(data.cart_count);
      // Show/hide footer based on count
      if (parseInt(data.cart_count) > 0) {
        $(".fc-mini-cart-footer").show();
      } else {
        $(".fc-mini-cart-footer").hide();
      }
    }
  }

  function fcRefreshMiniCart(callback) {
    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_get_mini_cart",
        nonce: fc_ajax.nonce,
      },
      success: function (res) {
        if (res.success) {
          fcUpdateMiniCart(res.data);
        }
        if (typeof callback === "function") callback();
      },
    });
  }

  // Close mini cart
  $(document).on(
    "click",
    ".fc-mini-cart-close, #fc-mini-cart-overlay",
    function () {
      fcCloseMiniCart();
    },
  );

  // Open mini cart via tab
  $(document).on("click", "#fc-mini-cart-tab", function (e) {
    e.stopPropagation();
    if (
      $("#fc-mini-cart").hasClass("active") &&
      $(".fc-panel-cart").hasClass("fc-panel-active")
    ) {
      fcCloseMiniCart();
    } else {
      fcRefreshMiniCart(function () {
        fcOpenMiniCart();
      });
    }
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $("#fc-mini-cart").hasClass("active")) {
      fcCloseMiniCart();
    }
  });

  // Remove item from mini cart
  $(document).on("click", ".fc-mini-cart-remove", function (e) {
    e.preventDefault();
    var $item = $(this).closest(".fc-mini-cart-item");
    var cartKey = $(this).data("cart-key");

    $item.css("opacity", "0.4");

    $.ajax({
      url: fc_ajax.url,
      type: "POST",
      data: {
        action: "fc_remove_from_cart",
        nonce: fc_ajax.nonce,
        product_id: cartKey,
      },
      success: function (res) {
        if (res.success) {
          fcRefreshMiniCart();
        }
      },
      error: function () {
        $item.css("opacity", "1");
        fcShowToast(fc_ajax.i18n.generic_error, "error");
      },
    });
  });

  // Mini cart quantity +/-
  $(document).on("click", ".fc-mini-qty-minus", function () {
    var $input = $(this).siblings(".fc-mini-qty-input");
    var val = parseInt($input.val()) || 1;
    if (val > 1) {
      $input.val(val - 1).trigger("change");
    }
  });

  $(document).on("click", ".fc-mini-qty-plus", function () {
    var $input = $(this).siblings(".fc-mini-qty-input");
    var val = parseInt($input.val()) || 1;
    var max = parseInt($input.attr("max")) || 99;
    if (val < max) {
      $input.val(val + 1).trigger("change");
    } else if (max < 99) {
      fcShowToast(
        fc_ajax.i18n.stock_limit.replace("%d", max).replace("%s", ""),
        "error",
      );
    }
  });

  var miniQtyTimer = null;
  $(document).on("change input", ".fc-mini-qty-input", function () {
    var $input = $(this);
    var cartKey = $input.data("cart-key");
    var max = parseInt($input.attr("max")) || 99;
    var qty = parseInt($input.val()) || 1;
    if (qty < 1) qty = 1;
    if (qty > max) {
      qty = max;
      if (max < 99) {
        fcShowToast(
          fc_ajax.i18n.stock_limit.replace("%d", max).replace("%s", ""),
          "error",
        );
      }
    }
    $input.val(qty);

    clearTimeout(miniQtyTimer);
    miniQtyTimer = setTimeout(function () {
      $.ajax({
        url: fc_ajax.url,
        type: "POST",
        data: {
          action: "fc_update_cart",
          nonce: fc_ajax.nonce,
          product_id: cartKey,
          quantity: qty,
        },
        success: function (res) {
          if (res.success) {
            fcRefreshMiniCart();
          }
        },
        error: function () {
          fcShowToast(fc_ajax.i18n.generic_error, "error");
        },
      });
    }, 400);
  });

  /* ===== Checkout: typ konta prywatne/firmowe ===== */
  function fcToggleAccountType(isCompany, animate) {
    var $companyFields = $(".fc-company-fields");
    var $privateFields = $(".fc-private-fields");
    var $companyInput = $("#billing_company, #billing_tax_no, #billing_crn");
    var $privateInput = $("#billing_first_name, #billing_last_name");
    // Shipping
    var $shipPrivate = $(".fc-shipping-private-fields");
    var $shipCompany = $(".fc-shipping-company-field");
    var $shipPrivateInput = $("#shipping_first_name, #shipping_last_name");
    var $shipCompanyInput = $("#shipping_company");

    if (isCompany) {
      if (animate) {
        $companyFields.slideDown(250);
        $privateFields.slideUp(250);
        $shipPrivate.slideUp(250);
        $shipCompany.slideDown(250);
      } else {
        $companyFields.show();
        $privateFields.hide();
        $shipPrivate.hide();
        $shipCompany.show();
      }
      $companyInput.attr("required", true);
      $privateInput.removeAttr("required").val("");
      $shipPrivateInput.removeAttr("required").val("");
      if ($('input[name="ship_to_different"]').is(":checked"))
        $shipCompanyInput.attr("required", true);
    } else {
      if (animate) {
        $companyFields.slideUp(250);
        $privateFields.slideDown(250);
        $shipPrivate.slideDown(250);
        $shipCompany.slideUp(250);
      } else {
        $companyFields.hide();
        $privateFields.show();
        $shipPrivate.show();
        $shipCompany.hide();
      }
      $companyInput.removeAttr("required").val("");
      $privateInput.attr("required", true);
      $shipCompanyInput.removeAttr("required").val("");
      if ($('input[name="ship_to_different"]').is(":checked"))
        $shipPrivateInput.attr("required", true);
    }
  }

  $(document).on("change", 'input[name="account_type"]', function () {
    fcToggleAccountType($(this).val() === "company", true);
  });

  // Inicjalizacja przy ładowaniu (np. po walidacji)
  (function () {
    var checked = $('input[name="account_type"]:checked').val();
    fcToggleAccountType(checked === "company", false);
  })();

  /* ===== Checkout: nazwy pól firmowych wg kraju ===== */
  var countryTaxLabels = {
    AL: {
      tax_no: "NIPT",
      reg: "Numri i Regjistrimit (QKR)",
      tax_noPh: "K12345678A",
      regPh: "K-12345678",
      tax_noRe: "^[KJ]\\d{8}[A-Z]$",
      regRe: ".",
    },
    AT: {
      tax_no: "UID (ATU)",
      reg: "Firmenbuchnummer (FN)",
      tax_noPh: "ATU12345678",
      regPh: "123456a",
      tax_noRe: "^ATU\\d{8}$",
      regRe: "^\\d{5,6}[a-z]$",
    },
    BY: {
      tax_no: "УНП",
      reg: "Рэгістрацыйны нумар",
      tax_noPh: "123456789",
      regPh: "123456789",
      tax_noRe: "^\\d{9}$",
      regRe: ".",
    },
    BE: {
      tax_no: "BTW / TVA",
      reg: "Ondernemingsnummer (KBO)",
      tax_noPh: "BE0123456789",
      regPh: "0123.456.789",
      tax_noRe: "^BE0\\d{9}$",
      regRe: "^0\\d{3}\\.\\d{3}\\.\\d{3}$",
    },
    BA: {
      tax_no: "PDV broj",
      reg: "Registarski broj",
      tax_noPh: "123456789012",
      regPh: "1-12345",
      tax_noRe: "^\\d{12}$",
      regRe: ".",
    },
    BG: {
      tax_no: "ИН по ДДС",
      reg: "ЕИК (Булстат)",
      tax_noPh: "BG123456789",
      regPh: "123456789",
      tax_noRe: "^BG\\d{9,10}$",
      regRe: "^\\d{9,13}$",
    },
    HR: {
      tax_no: "OIB",
      reg: "Matični broj subjekta (MBS)",
      tax_noPh: "12345678901",
      regPh: "080012345",
      tax_noRe: "^\\d{11}$",
      regRe: "^\\d{9}$",
    },
    CY: {
      tax_no: "Αριθμός ΦΠΑ",
      reg: "Αριθμός Εγγραφής (HE)",
      tax_noPh: "CY12345678X",
      regPh: "HE123456",
      tax_noRe: "^CY\\d{8}[A-Z]$",
      regRe: "^HE\\d{5,6}$",
    },
    ME: {
      tax_no: "PIB",
      reg: "Registarski broj",
      tax_noPh: "12345678",
      regPh: "12345678",
      tax_noRe: "^\\d{8}$",
      regRe: ".",
    },
    CZ: {
      tax_no: "DIČ",
      reg: "Identifikační číslo (IČO)",
      tax_noPh: "CZ12345678",
      regPh: "12345678",
      tax_noRe: "^CZ\\d{8,10}$",
      regRe: "^\\d{8}$",
    },
    DK: {
      tax_no: "SE-nummer",
      reg: "CVR-nummer",
      tax_noPh: "DK12345678",
      regPh: "12345678",
      tax_noRe: "^DK\\d{8}$",
      regRe: "^\\d{8}$",
    },
    EE: {
      tax_no: "KMKR number",
      reg: "Registrikood",
      tax_noPh: "EE123456789",
      regPh: "12345678",
      tax_noRe: "^EE\\d{9}$",
      regRe: "^\\d{8}$",
    },
    FI: {
      tax_no: "ALV-numero",
      reg: "Y-tunnus",
      tax_noPh: "FI12345678",
      regPh: "1234567-8",
      tax_noRe: "^FI\\d{8}$",
      regRe: "^\\d{7}-\\d$",
    },
    FR: {
      tax_no: "Numéro de TVA",
      reg: "Numéro SIREN / SIRET",
      tax_noPh: "FR12345678901",
      regPh: "123 456 789 00012",
      tax_noRe: "^FR[A-Z0-9]{2}\\d{9}$",
      regRe: "^\\d{3}\\s?\\d{3}\\s?\\d{3}(\\s?\\d{5})?$",
    },
    GR: {
      tax_no: "ΑΦΜ",
      reg: "Αριθμός ΓΕΜΗ",
      tax_noPh: "EL123456789",
      regPh: "12345678901",
      tax_noRe: "^EL\\d{9}$",
      regRe: "^\\d{11,12}$",
    },
    ES: {
      tax_no: "NIF / CIF",
      reg: "Registro Mercantil",
      tax_noPh: "B12345678",
      regPh: "T 12345 , 1ª",
      tax_noRe: "^[A-Z]\\d{7}[A-Z0-9]$",
      regRe: ".",
    },
    NL: {
      tax_no: "BTW-nummer",
      reg: "KVK-nummer",
      tax_noPh: "NL123456789B01",
      regPh: "12345678",
      tax_noRe: "^NL\\d{9}B\\d{2}$",
      regRe: "^\\d{8}$",
    },
    IE: {
      tax_no: "VAT Number",
      reg: "Company Registration (CRO)",
      tax_noPh: "IE1234567AB",
      regPh: "123456",
      tax_noRe: "^IE\\d{7}[A-Z]{1,2}$",
      regRe: "^\\d{5,6}$",
    },
    IS: {
      tax_no: "Virðisaukaskattnúmer (VSK)",
      reg: "Kennitala",
      tax_noPh: "123456",
      regPh: "1234567890",
      tax_noRe: "^\\d{5,6}$",
      regRe: "^\\d{10}$",
    },
    LT: {
      tax_no: "PVM mokėtojo kodas",
      reg: "Įmonės kodas",
      tax_noPh: "LT123456789012",
      regPh: "123456789",
      tax_noRe: "^LT\\d{9,12}$",
      regRe: "^\\d{7,9}$",
    },
    LU: {
      tax_no: "Numéro TVA",
      reg: "Numéro RCS",
      tax_noPh: "LU12345678",
      regPh: "B123456",
      tax_noRe: "^LU\\d{8}$",
      regRe: "^[A-Z]\\d{5,6}$",
    },
    LV: {
      tax_no: "PVN numurs",
      reg: "Reģistrācijas Nr.",
      tax_noPh: "LV12345678901",
      regPh: "40003012345",
      tax_noRe: "^LV\\d{11}$",
      regRe: "^\\d{11}$",
    },
    MK: {
      tax_no: "ДДВ број",
      reg: "ЕМБС (Единствен матичен број)",
      tax_noPh: "MK1234567890123",
      regPh: "1234567",
      tax_noRe: "^MK\\d{13}$",
      regRe: "^\\d{7}$",
    },
    MT: {
      tax_no: "VAT Number",
      reg: "Company Number (C)",
      tax_noPh: "MT12345678",
      regPh: "C 12345",
      tax_noRe: "^MT\\d{8}$",
      regRe: "^C\\s?\\d{4,6}$",
    },
    MD: {
      tax_no: "Codul TVA",
      reg: "IDNO (Cod fiscal)",
      tax_noPh: "1234567",
      regPh: "1234567890123",
      tax_noRe: "^\\d{7}$",
      regRe: "^\\d{13}$",
    },
    DE: {
      tax_no: "Umsatzsteuer-IdNr.",
      reg: "Handelsregisternummer (HRB)",
      tax_noPh: "DE123456789",
      regPh: "HRB 12345",
      tax_noRe: "^DE\\d{9}$",
      regRe: "^HR[AB]\\s?\\d{4,6}$",
    },
    NO: {
      tax_no: "MVA-nummer",
      reg: "Organisasjonsnummer",
      tax_noPh: "NO123456789MVA",
      regPh: "123456789",
      tax_noRe: "^NO\\d{9}MVA$",
      regRe: "^\\d{9}$",
    },
    PL: {
      tax_no: "NIP",
      reg: "KRS / REGON",
      tax_noPh: "1234567890",
      regPh: "0000012345",
      tax_noRe: "^\\d{10}$",
      regRe: "^\\d{9,14}$",
    },
    PT: {
      tax_no: "Número de contribuinte (NIF)",
      reg: "NIPC",
      tax_noPh: "PT123456789",
      regPh: "123456789",
      tax_noRe: "^PT\\d{9}$",
      regRe: "^\\d{9}$",
    },
    RO: {
      tax_no: "Cod de identificare fiscală (CIF)",
      reg: "Nr. Registrul Comerțului",
      tax_noPh: "RO12345678",
      regPh: "J40/1234/2020",
      tax_noRe: "^RO\\d{2,10}$",
      regRe: "^J\\d{2}\\/\\d{1,6}\\/\\d{4}$",
    },
    RS: {
      tax_no: "ПИБ",
      reg: "Матични број",
      tax_noPh: "123456789",
      regPh: "12345678",
      tax_noRe: "^\\d{9}$",
      regRe: "^\\d{8}$",
    },
    SK: {
      tax_no: "IČ DPH",
      reg: "Identifikačné číslo (IČO)",
      tax_noPh: "SK1234567890",
      regPh: "12345678",
      tax_noRe: "^SK\\d{10}$",
      regRe: "^\\d{6,8}$",
    },
    SI: {
      tax_no: "Identifikacijska št. za DDV",
      reg: "Matična številka",
      tax_noPh: "SI12345678",
      regPh: "1234567000",
      tax_noRe: "^SI\\d{8}$",
      regRe: "^\\d{10}$",
    },
    CH: {
      tax_no: "MWST-Nr. / Numéro TVA",
      reg: "Unternehmens-Id. (CHE/UID)",
      tax_noPh: "CHE-123.456.789",
      regPh: "CHE-123.456.789",
      tax_noRe: "^CHE-?\\d{3}\\.?\\d{3}\\.?\\d{3}$",
      regRe: "^CHE-?\\d{3}\\.?\\d{3}\\.?\\d{3}$",
    },
    SE: {
      tax_no: "Momsregistreringsnummer",
      reg: "Organisationsnummer",
      tax_noPh: "SE123456789012",
      regPh: "123456-1234",
      tax_noRe: "^SE\\d{12}$",
      regRe: "^\\d{6}-\\d{4}$",
    },
    UA: {
      tax_no: "Індивідуальний податковий номер (ІПН)",
      reg: "Код ЄДРПОУ",
      tax_noPh: "1234567890",
      regPh: "12345678",
      tax_noRe: "^\\d{10,12}$",
      regRe: "^\\d{8}$",
    },
    HU: {
      tax_no: "Adószám",
      reg: "Cégjegyzékszám",
      tax_noPh: "12345678-1-23",
      regPh: "01-23-123456",
      tax_noRe: "^\\d{8}-\\d-\\d{2}$",
      regRe: "^\\d{2}-\\d{2}-\\d{6}$",
    },
    GB: {
      tax_no: "VAT Registration Number",
      reg: "Company Registration Number",
      tax_noPh: "GB123456789",
      regPh: "12345678",
      tax_noRe: "^GB\\d{9,12}$",
      regRe: "^\\d{8}$",
    },
    IT: {
      tax_no: "Partita IVA",
      reg: "Numero REA",
      tax_noPh: "IT12345678901",
      regPh: "MI-1234567",
      tax_noRe: "^IT\\d{11}$",
      regRe: "^[A-Z]{2}-\\d{5,7}$",
    },
  };

  function fcUpdateCompanyLabels(countryCode) {
    var data = countryTaxLabels[countryCode] || {
      tax_no: fc_ajax.i18n.tax_no_default,
      reg: fc_ajax.i18n.reg_no_default,
      tax_noPh: "",
      regPh: "",
      tax_noRe: ".",
      regRe: ".",
    };
    $("#billing_tax_no_label").html(
      data.tax_no + ' <span class="fc-required">*</span>',
    );
    $("#billing_crn_label").html(
      data.reg + ' <span class="fc-required">*</span>',
    );
    $("#billing_tax_no")
      .attr("placeholder", data.tax_noPh || "")
      .attr("pattern", data.tax_noRe || ".*")
      .attr("title", data.tax_no + ": " + (data.tax_noPh || ""));
    $("#billing_crn")
      .attr("placeholder", data.regPh || "")
      .attr("pattern", data.regRe || ".*")
      .attr("title", data.reg + ": " + (data.regPh || ""));
  }

  $(document).on("change", "#billing_country", function () {
    fcUpdateCompanyLabels($(this).val());
    fcFilterShippingMethods($(this).val());
  });

  // Filtruj metody wysyłki wg wybranego kraju
  function fcFilterShippingMethods(country) {
    var $container = $(".fc-shipping-methods");
    if (!$container.length) return;

    var visibleCount = 0;
    $container.find(".fc-shipping-option").each(function () {
      var countries = ($(this).data("countries") || "").toString();
      if (!countries) {
        // Puste = wszystkie kraje — zawsze widoczna
        $(this).show();
        visibleCount++;
      } else {
        var list = countries.split(",");
        if (list.indexOf(country) !== -1) {
          $(this).show();
          visibleCount++;
        } else {
          $(this).hide();
          // Odznacz jeśli była zaznaczona
          $(this).find('input[type="radio"]').prop("checked", false);
        }
      }
    });

    // Ukryj/pokaż sekcję i nagłówek
    $(".fc-shipping-methods-heading").toggle(visibleCount > 0);
    $container.toggle(visibleCount > 0);

    // Jeśli tylko jedna opcja widoczna — zaznacz automatycznie
    if (visibleCount === 1) {
      $container
        .find(".fc-shipping-option:visible input[type='radio']")
        .prop("checked", true);
    }
  }

  // Inicjalizacja etykiet przy ładowaniu
  (function () {
    var cc = $("#billing_country").val();
    if (cc) {
      fcUpdateCompanyLabels(cc);
      fcFilterShippingMethods(cc);
    }
  })();

  /* ===== Checkout: wysyłka na inny adres ===== */
  function fcToggleShipping(show, animate) {
    var $shippingFields = $(".fc-shipping-fields");
    var $common = $shippingFields.find(
      "#shipping_address, #shipping_city, #shipping_postcode",
    );
    var isCompany = $('input[name="account_type"]:checked').val() === "company";
    var $nameInputs = $("#shipping_first_name, #shipping_last_name");
    var $companyInput = $("#shipping_company");

    if (show) {
      if (animate) $shippingFields.slideDown(250);
      else $shippingFields.show();
      $common.attr("required", true);
      if (isCompany) {
        $companyInput.attr("required", true);
        $nameInputs.removeAttr("required");
      } else {
        $nameInputs.attr("required", true);
        $companyInput.removeAttr("required");
      }
    } else {
      if (animate) $shippingFields.slideUp(250);
      else $shippingFields.hide();
      $common.removeAttr("required").val("");
      $nameInputs.removeAttr("required").val("");
      $companyInput.removeAttr("required").val("");
    }
  }

  $(document).on("change", 'input[name="ship_to_different"]', function () {
    fcToggleShipping($(this).is(":checked"), true);
  });

  // Inicjalizacja przy ładowaniu
  (function () {
    fcToggleShipping(
      $('input[name="ship_to_different"]').is(":checked"),
      false,
    );
  })();

  /* ===== Zakładki produktu ===== */
  $(document).on("click", ".fc-tab-btn", function () {
    var tab = $(this).data("tab");
    $(this)
      .closest(".fc-product-tabs")
      .find(".fc-tab-btn")
      .removeClass("active");
    $(this).addClass("active");
    $(this)
      .closest(".fc-product-tabs")
      .find(".fc-tab-panel")
      .removeClass("active");
    $(this)
      .closest(".fc-product-tabs")
      .find('.fc-tab-panel[data-tab="' + tab + '"]')
      .addClass("active");
  });

  // Otwieraj zakładkę Opinie z anchora #fc-reviews
  (function () {
    if (window.location.hash === "#fc-reviews") {
      $('.fc-tab-btn[data-tab="reviews"]').trigger("click");
    }
  })();

  /* ===== Interaktywna ocena gwiazdkowa z połówkami ===== */
  (function () {
    var $wrap = $(".fc-star-rating-input");
    if (!$wrap.length) return;

    var $stars = $wrap.find(".fc-input-star");
    var $hidden = $wrap.find('input[name="fc_rating"]');
    var $text = $wrap.find(".fc-rating-text");
    var selectedRating = 0;

    function getRatingFromEvent(e, $star) {
      var rect = $star[0].getBoundingClientRect();
      var x = e.clientX - rect.left;
      var half = x < rect.width / 2;
      var base = parseFloat($star.data("value"));
      return half ? base - 0.5 : base;
    }

    function applyStars($container, rating) {
      $container.find(".fc-input-star").each(function () {
        var val = parseFloat($(this).data("value"));
        $(this).removeClass("fc-star-full fc-star-half fc-star-empty");
        // Reset inline styles for gradient
        $(this).css({
          background: "",
          "-webkit-background-clip": "",
          "-webkit-text-fill-color": "",
          "background-clip": "",
          color: "",
        });

        if (rating >= val) {
          $(this).addClass("fc-star-full").css("color", "#f5a623");
        } else if (rating >= val - 0.5) {
          $(this).addClass("fc-star-half").css({
            background: "linear-gradient(90deg, #f5a623 50%, #ddd 50%)",
            "-webkit-background-clip": "text",
            "-webkit-text-fill-color": "transparent",
            "background-clip": "text",
            color: "transparent",
          });
        } else {
          $(this).addClass("fc-star-empty").css("color", "#ddd");
        }
      });
    }

    function formatRating(r) {
      if (r === 0) return "";
      return r % 1 === 0 ? r + ".0 / 5" : r + " / 5";
    }

    $stars.on("mousemove", function (e) {
      var rating = getRatingFromEvent(e, $(this));
      applyStars($wrap, rating);
      $text.text(formatRating(rating));
    });

    $wrap.on("mouseleave", function () {
      applyStars($wrap, selectedRating);
      $text.text(formatRating(selectedRating));
    });

    $stars.on("click", function (e) {
      selectedRating = getRatingFromEvent(e, $(this));
      $hidden.val(selectedRating);
      applyStars($wrap, selectedRating);
      $text.text(formatRating(selectedRating));
      // Usuń ewentualny komunikat błędu po wybraniu oceny
      $wrap.siblings(".fc-field-error").remove();
      $wrap.closest(".fc-field").removeClass("fc-field-invalid");
    });

    // Walidacja formularza opinii przed wysłaniem
    $(".fc-review-form").on("submit", function (e) {
      var $form = $(this);
      var rating = parseFloat($form.find('input[name="fc_rating"]').val());
      var content = $.trim(
        $form.find('textarea[name="fc_review_content"]').val(),
      );
      var hasError = false;

      // Usuń poprzednie błędy
      $form.find(".fc-field-error").remove();
      $form.find(".fc-field").removeClass("fc-field-invalid");

      if (!rating || rating < 0.5) {
        $wrap.closest(".fc-field").addClass("fc-field-invalid");
        $wrap.after(
          '<span class="fc-field-error" style="display:block;">' +
            fc_ajax.i18n.review_rating_required +
            "</span>",
        );
        hasError = true;
      }

      if (!content) {
        var $textarea = $form.find('textarea[name="fc_review_content"]');
        $textarea.closest(".fc-field").addClass("fc-field-invalid");
        $textarea.after(
          '<span class="fc-field-error" style="display:block;">' +
            fc_ajax.i18n.review_content_required +
            "</span>",
        );
        hasError = true;
      }

      if (hasError) {
        e.preventDefault();
        return false;
      }
    });

    // Czyść błąd textarea przy wpisywaniu
    $(".fc-review-form textarea[name='fc_review_content']").on(
      "input",
      function () {
        $(this).siblings(".fc-field-error").remove();
        $(this).closest(".fc-field").removeClass("fc-field-invalid");
      },
    );
  })();

  /* ===== Checkout: Wizard kroków ===== */
  var fcCurrentStep = 1;
  var fcTotalSteps = 4;
  var fcIsOnepage =
    fc_ajax.checkout_layout === "onepage" ||
    fc_ajax.checkout_layout === "twocol";

  // Helper: formatowanie ceny
  var fcCurrencySymbol = fc_ajax.currency;
  var fcCurrencyPos = fc_ajax.currency_pos || "after";

  function fcFormatPriceCheckout(val) {
    var f = parseFloat(val).toFixed(2);
    var parts = f.split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    var formatted = parts[0] + "," + parts[1];
    if (fcCurrencyPos === "before") return fcCurrencySymbol + " " + formatted;
    return formatted + " " + fcCurrencySymbol;
  }

  function fcGoToStep(step) {
    if (step < 1 || step > fcTotalSteps) return;

    if (fcIsOnepage) {
      // W trybie onepage: scrolluj do odpowiedniej sekcji
      var $target = $('.fc-checkout-step[data-step="' + step + '"]');
      if ($target.length) {
        $("html, body").animate({ scrollTop: $target.offset().top - 30 }, 300);
      }
      // Aktualizuj pasek kroków
      $(".fc-steps .fc-step").each(function () {
        var s = parseInt($(this).attr("data-step"));
        $(this)
          .removeClass("active completed")
          .addClass(s === step ? "active" : s < step ? "completed" : "");
      });
      $(".fc-steps .fc-step-line").each(function (i) {
        $(this).toggleClass("completed", i + 1 < step);
      });
      fcCurrentStep = step;
      if (step === 4) fcPopulateSummary();
      return;
    }

    // Ukryj aktywne pola required w ukrywanych krokach
    $(".fc-checkout-step.active")
      .find("[required]")
      .each(function () {
        $(this).attr("data-required", "1").removeAttr("required");
      });

    // Zmiana paneli
    $(".fc-checkout-step").removeClass("active");
    $('.fc-checkout-step[data-step="' + step + '"]').addClass("active");

    // Przywróć required w widocznym kroku
    $('.fc-checkout-step[data-step="' + step + '"]')
      .find("[data-required]")
      .each(function () {
        $(this).attr("required", true).removeAttr("data-required");
      });

    // Aktualizacja paska kroków
    $(".fc-steps .fc-step").each(function () {
      var s = parseInt($(this).attr("data-step"));
      $(this)
        .removeClass("active completed")
        .addClass(s === step ? "active" : s < step ? "completed" : "");
    });
    $(".fc-steps .fc-step-line").each(function (i) {
      $(this).toggleClass("completed", i + 1 < step);
    });

    fcCurrentStep = step;

    // Scroll do góry formularza
    $("html, body").animate(
      { scrollTop: $(".fc-steps").offset().top - 30 },
      300,
    );

    // Wypełnij podsumowanie przy kroku 4
    if (step === 4) {
      fcPopulateSummary();
    }
  }

  function fcValidateStep(step) {
    var $step = $('.fc-checkout-step[data-step="' + step + '"]');
    var valid = true;
    var $firstInvalid = null;

    // Validate all visible required / data-required fields using inline validation
    $step
      .find(
        "input[required], textarea[required], select[required], [data-required]",
      )
      .each(function () {
        var $el = $(this);
        if (!$el.is(":visible")) return;
        if (!window.fcValidateField($el)) {
          valid = false;
          if (!$firstInvalid) $firstInvalid = $el;
        }
      });

    // Check fields already marked invalid (e.g. email-exists from AJAX)
    $step.find(".fc-field-invalid").each(function () {
      var $input = $(this).find("input, textarea, select").first();
      if ($input.is(":visible")) {
        valid = false;
        if (!$firstInvalid) $firstInvalid = $input;
      }
    });

    // Walidacja wyboru metody wysyłki (krok 2)
    if (step === 2 && $(".fc-shipping-methods").length) {
      if (!$('input[name="shipping_method"]:checked').length) {
        alert(fc_ajax.i18n.select_shipping_method);
        valid = false;
      }
    }

    // Scroll to first invalid field
    if ($firstInvalid && $firstInvalid.length) {
      $("html, body").animate(
        { scrollTop: $firstInvalid.closest(".fc-field").offset().top - 80 },
        300,
      );
      $firstInvalid.focus();
    }

    return valid;
  }

  // Helper: escape HTML for safe DOM insertion (including attribute-safe quotes)
  function esc(s) {
    return $("<span>")
      .text(s || "")
      .html()
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function fcPopulateSummary() {
    var isCompany = $('input[name="account_type"]:checked').val() === "company";

    // Dane rozliczeniowe
    var billingHtml = "";
    var billingCountry = ($("#billing_country").val() || "PL").toUpperCase();
    var countryLabels = countryTaxLabels[billingCountry] || {
      tax_no: fc_ajax.i18n.tax_no_default,
      reg: fc_ajax.i18n.reg_no_default,
    };
    if (isCompany) {
      billingHtml +=
        "<p><strong>" + esc($("#billing_company").val()) + "</strong></p>";
    } else {
      billingHtml +=
        "<p><strong>" +
        esc($("#billing_first_name").val()) +
        " " +
        esc($("#billing_last_name").val()) +
        "</strong></p>";
    }
    billingHtml += "<p>" + esc($("#billing_address").val()) + "</p>";
    billingHtml +=
      "<p>" +
      esc($("#billing_postcode").val()) +
      " " +
      esc($("#billing_city").val()) +
      ", " +
      esc(billingCountry) +
      "</p>";
    billingHtml += "<p>" + esc($("#billing_email").val()) + "</p>";
    var phonePrefix = $(".fc-phone-wrap .fc-prefix-code").first().text() || "";
    billingHtml +=
      "<p>" + esc(phonePrefix) + " " + esc($("#billing_phone").val()) + "</p>";
    if (isCompany) {
      billingHtml +=
        "<p>" +
        esc(countryLabels.tax_no) +
        ": " +
        esc($("#billing_tax_no").val()) +
        "</p>";
      if ($("#billing_crn").val()) {
        billingHtml +=
          "<p>" +
          esc(countryLabels.reg) +
          ": " +
          esc($("#billing_crn").val()) +
          "</p>";
      }
    }
    $(".fc-summary-billing-info").html(billingHtml);

    // Adres wysyłki
    var shipDiff = $('input[name="ship_to_different"]').is(":checked");
    var shippingHtml = "";
    if (shipDiff) {
      if (isCompany) {
        shippingHtml +=
          "<p><strong>" + esc($("#shipping_company").val()) + "</strong></p>";
      } else {
        shippingHtml +=
          "<p><strong>" +
          esc($("#shipping_first_name").val()) +
          " " +
          esc($("#shipping_last_name").val()) +
          "</strong></p>";
      }
      shippingHtml += "<p>" + esc($("#shipping_address").val()) + "</p>";
      var shippingCountry = (
        $("#shipping_country").val() || "PL"
      ).toUpperCase();
      shippingHtml +=
        "<p>" +
        esc($("#shipping_postcode").val()) +
        " " +
        esc($("#shipping_city").val()) +
        ", " +
        esc(shippingCountry) +
        "</p>";
    } else {
      shippingHtml += "<p>" + fc_ajax.i18n.same_as_billing + "</p>";
    }
    $(".fc-summary-shipping-info").html(shippingHtml);

    // Metoda wysyłki
    var $selectedShipping = $('input[name="shipping_method"]:checked');
    var shippingMethodHtml = "";
    var shippingCost = 0;
    if ($selectedShipping.length) {
      var smName = $selectedShipping.data("name") || "";
      var smCost = parseFloat($selectedShipping.data("cost")) || 0;
      var $smContainer = $(".fc-shipping-methods");
      var freeThreshold = parseFloat($smContainer.data("free-threshold")) || 0;
      var cartTotal = parseFloat($smContainer.data("cart-total")) || 0;
      var isFree = freeThreshold > 0 && cartTotal >= freeThreshold;
      shippingCost = isFree ? 0 : smCost;
      shippingMethodHtml =
        "<p><strong>" +
        esc(smName) +
        "</strong> — " +
        (shippingCost > 0
          ? fcFormatPriceCheckout(shippingCost)
          : esc(fc_ajax.i18n.free_shipping)) +
        "</p>";
    }
    $(".fc-summary-shipping-method-info").html(shippingMethodHtml);

    // Aktualizuj wiersz wysyłki w tabeli
    if (shippingCost > 0) {
      $(".fc-summary-shipping-row")
        .show()
        .find(".fc-summary-shipping-cost")
        .html(fcFormatPriceCheckout(shippingCost));
    } else if ($selectedShipping.length) {
      $(".fc-summary-shipping-row")
        .show()
        .find(".fc-summary-shipping-cost")
        .html(fc_ajax.i18n.free_shipping);
    } else {
      $(".fc-summary-shipping-row").hide();
    }

    // Aktualizuj sumę z wysyłką
    var $grandTotal = $(".fc-summary-grand-total");
    var baseTotal = parseFloat($grandTotal.data("cart-total")) || 0;
    var couponDiscount = parseFloat($grandTotal.data("coupon-discount")) || 0;
    $grandTotal.html(
      fcFormatPriceCheckout(
        Math.max(0, baseTotal - couponDiscount + shippingCost),
      ),
    );

    // Płatność
    var paymentVal = $('input[name="payment_method"]:checked').val() || "";
    var paymentLabel =
      $('input[name="payment_method"]:checked').data("label") || paymentVal;
    $(".fc-summary-payment-info").html("<p>" + esc(paymentLabel) + "</p>");
  }

  /* ===== Inline field validation (blur / change) ===== */
  (function () {
    var emailCheckTimer = null;
    var emailCheckXhr = null;

    // Helper: mark field
    function markField($field, valid, msg) {
      var $wrap = $field.closest(".fc-field");
      if (!$wrap.length) return;
      $wrap.removeClass("fc-field-invalid fc-field-valid");
      $wrap.find(".fc-field-error").remove();
      if (valid === true) {
        $wrap.addClass("fc-field-valid");
      } else if (valid === false) {
        $wrap.addClass("fc-field-invalid");
        if (msg) {
          $wrap.append('<span class="fc-field-error">' + msg + "</span>");
        }
      }
    }

    // Validate a single field, return true/false
    function validateField($el) {
      var val = $.trim($el.val());
      var name = $el.attr("name") || "";
      var type = $el.attr("type") || "text";

      // Skip hidden / non-visible
      if (!$el.is(":visible")) return true;

      // Required check
      var isRequired = $el.is("[required]") || $el.is("[data-required]");
      if (isRequired && !val) {
        markField($el, false, fc_ajax.i18n.field_required);
        return false;
      }

      // Username min length (registration)
      if (name === "reg_username" && val) {
        if (val.length < 3) {
          markField($el, false, fc_ajax.i18n.username_min_length);
          return false;
        }
        if (/\s/.test(val)) {
          markField($el, false, fc_ajax.i18n.username_no_spaces);
          return false;
        }
      }

      // Email format
      if (type === "email" && val) {
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(val)) {
          markField($el, false, fc_ajax.i18n.invalid_email);
          return false;
        }
      }

      // Password min length
      if (
        (name === "fc_reg_password" ||
          name === "fc_new_password" ||
          name === "reg_password") &&
        val &&
        val.length < 6
      ) {
        markField($el, false, fc_ajax.i18n.password_min_length);
        return false;
      }

      // Password confirm match
      if ((name === "fc_reg_password2" || name === "reg_password2") && val) {
        var pwd1 =
          name === "reg_password2"
            ? $("#fc_reg_password").val() ||
              $("input[name='reg_password']").val() ||
              ""
            : $("#fc_reg_password").val() || "";
        if (val !== pwd1) {
          markField($el, false, fc_ajax.i18n.passwords_mismatch);
          return false;
        }
      }
      if (name === "fc_new_password2" && val) {
        var pwd1 = $("#fc_new_password").val() || "";
        if (val !== pwd1) {
          markField($el, false, fc_ajax.i18n.passwords_mismatch);
          return false;
        }
      }

      // Activation code — exactly 6 digits
      if (name === "fc_activation_code" && val) {
        if (!/^\d{6}$/.test(val)) {
          markField($el, false, fc_ajax.i18n.activation_code_format);
          return false;
        }
      }

      // If we got here, field is valid
      if (val) {
        markField($el, true);
      } else {
        markField($el, null); // neutral (empty optional)
      }
      return true;
    }

    // AJAX email-exists check (only for guests)
    function checkEmailExists($el) {
      var val = $.trim($el.val());
      if (!val) return;
      var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRe.test(val)) return;

      // Only for non-logged-in users
      if ($(".fc-checkout-register").length === 0) return;

      if (emailCheckXhr) emailCheckXhr.abort();
      emailCheckXhr = $.post(fc_ajax.url, {
        action: "fc_check_email",
        nonce: fc_ajax.nonce,
        email: val,
      }).done(function (res) {
        if (res.success && res.data.exists) {
          markField($el, false, fc_ajax.i18n.email_exists_login);
        }
      });
    }

    // Blur handler for text/email/password/tel inputs
    $(document).on(
      "blur",
      ".fc-checkout-step input[type=text], .fc-checkout-step input[type=email], .fc-checkout-step input[type=password], .fc-checkout-step input[type=tel], .fc-checkout-step textarea, .fc-account-form input[type=text], .fc-account-form input[type=email], .fc-account-form input[type=password], .fc-account-form input[type=tel], .fc-account-form textarea, .fc-auth-box input[type=text], .fc-auth-box input[type=email], .fc-auth-box input[type=password], .fc-auth-box input[type=tel]",
      function () {
        var $el = $(this);
        validateField($el);

        // Debounced email-exists check (checkout)
        if ($el.attr("name") === "billing_email") {
          clearTimeout(emailCheckTimer);
          emailCheckTimer = setTimeout(function () {
            if ($el.closest(".fc-field").hasClass("fc-field-valid")) {
              checkEmailExists($el);
            }
          }, 300);
        }

        // AJAX: username availability check (registration)
        if (
          $el.attr("name") === "reg_username" &&
          $el.closest(".fc-field").hasClass("fc-field-valid")
        ) {
          clearTimeout(emailCheckTimer);
          emailCheckTimer = setTimeout(function () {
            fcCheckUsernameExists($el);
          }, 300);
        }

        // AJAX: email availability check (registration)
        if (
          $el.attr("name") === "reg_email" &&
          $el.closest(".fc-field").hasClass("fc-field-valid")
        ) {
          clearTimeout(emailCheckTimer);
          emailCheckTimer = setTimeout(function () {
            fcCheckRegEmailExists($el);
          }, 300);
        }
      },
    );

    // For country selects (hidden input changes via custom dropdown)
    $(document).on("fc:countryChange", function (e, data) {
      if (data && data.name) {
        var $input = $('input[name="' + data.name + '"]');
        if ($input.val()) {
          markField($input, true);
        }
      }
    });

    // Revalidate password2 when password1 changes
    $(document).on("blur", "#fc_reg_password", function () {
      var $pwd2 = $("#fc_reg_password2");
      if (!$pwd2.length) $pwd2 = $("input[name='reg_password2']");
      if ($.trim($pwd2.val())) {
        validateField($pwd2);
      }
    });

    // AJAX: check username availability
    var usernameCheckXhr = null;
    function fcCheckUsernameExists($el) {
      var val = $.trim($el.val());
      if (!val || val.length < 3) return;
      if (usernameCheckXhr) usernameCheckXhr.abort();
      usernameCheckXhr = $.post(fc_ajax.url, {
        action: "fc_check_username",
        nonce: fc_ajax.nonce,
        username: val,
      }).done(function (res) {
        if (res.success && res.data.exists) {
          markField($el, false, fc_ajax.i18n.username_taken);
        }
      });
    }

    // AJAX: check email availability (for registration form)
    var regEmailCheckXhr = null;
    function fcCheckRegEmailExists($el) {
      var val = $.trim($el.val());
      if (!val) return;
      var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRe.test(val)) return;
      if (regEmailCheckXhr) regEmailCheckXhr.abort();
      regEmailCheckXhr = $.post(fc_ajax.url, {
        action: "fc_check_email",
        nonce: fc_ajax.nonce,
        email: val,
      }).done(function (res) {
        if (res.success && res.data.exists) {
          markField($el, false, fc_ajax.i18n.email_already_registered);
        }
      });
    }

    // Revalidate reset password2 when password1 changes
    $(document).on("blur", "#fc_new_password", function () {
      var $pwd2 = $("#fc_new_password2");
      if ($.trim($pwd2.val())) {
        validateField($pwd2);
      }
    });

    // Live validation on reset password fields
    $(document).on("blur", "#fc_new_password, #fc_new_password2", function () {
      validateField($(this));
    });

    // Live validation on activation code — only digits, auto-trim
    $(document).on("input", "#fc_activation_code", function () {
      // Allow only digits
      this.value = this.value.replace(/[^0-9]/g, "").substring(0, 6);
      if (this.value.length === 6) {
        validateField($(this));
      }
    });
    $(document).on("blur", "#fc_activation_code", function () {
      validateField($(this));
    });

    // Clear error on focus
    $(document).on(
      "focus",
      ".fc-field-invalid input, .fc-field-invalid textarea",
      function () {
        var $wrap = $(this).closest(".fc-field");
        $wrap.removeClass("fc-field-invalid");
        $wrap.find(".fc-field-error").remove();
      },
    );

    // Expose for step validation
    window.fcValidateField = validateField;
    window.fcMarkField = markField;
    window.fcCheckEmailExists = checkEmailExists;
  })();

  // Registration form submit validation (My Account page)
  $(document).on("submit", ".fc-auth-box form", function (e) {
    var $form = $(this);
    // Only validate the registration form (has fc_action=register)
    if ($form.find('input[name="fc_action"][value="register"]').length === 0)
      return;

    var allValid = true;
    $form.find("input[required]:visible").each(function () {
      if (!fcValidateField($(this))) {
        allValid = false;
      }
    });

    // Also check if any field already has AJAX error (username/email taken)
    if ($form.find(".fc-field-invalid").length > 0) {
      allValid = false;
    }

    if (!allValid) {
      e.preventDefault();
      // Scroll to first error
      var $firstErr = $form.find(".fc-field-invalid:first");
      if ($firstErr.length) {
        $("html, body").animate(
          { scrollTop: $firstErr.offset().top - 100 },
          300,
        );
        $firstErr.find("input").focus();
      }
    }
  });

  // Nawigacja — przycisk "Dalej"
  $(document).on("click", ".fc-step-next", function () {
    if (fcValidateStep(fcCurrentStep)) {
      fcGoToStep(fcCurrentStep + 1);
    }
  });

  // Nawigacja — przycisk "Wstecz"
  $(document).on("click", ".fc-step-prev", function () {
    fcGoToStep(fcCurrentStep - 1);
  });

  // Kliknięcie na krok w pasku — nawigacja do sekcji (krokowy: do ukończonych / aktywnego)
  $(document).on("click", ".fc-steps .fc-step", function () {
    var step = parseInt($(this).attr("data-step"));
    if (fcIsOnepage) {
      fcGoToStep(step);
    } else if ($(this).hasClass("completed") || $(this).hasClass("active")) {
      fcGoToStep(step);
    }
  });

  // Inicjalizacja: ukryj required w nieaktywnych krokach (tylko w trybie krokowym)
  (function () {
    if (fcIsOnepage) {
      // W trybie onepage: pokaż wszystkie kroki, ukryj przyciski nawigacji
      $(".fc-checkout-step").addClass("active");
      $(".fc-step-next, .fc-step-prev").hide();
      // Pokaż submit tylko w ostatnim kroku
      $(".fc-checkout-step")
        .not('[data-step="4"]')
        .find(".fc-step-actions")
        .hide();

      // Twocol: przenieś elementy do prawej kolumny
      if (fc_ajax.checkout_layout === "twocol") {
        var $form = $(".fc-checkout-form");
        var $rightCol = $('<div class="fc-twocol-right"></div>');

        // 1. Tabela produktów (krok 4) — na górze
        $rightCol.append($form.find('.fc-checkout-step[data-step="4"]'));

        // 2. Metoda wysyłki — wyciągnij z kroku 2
        var $shipHeading = $form.find(".fc-shipping-methods-heading");
        var $shipMethods = $form.find(".fc-shipping-methods");
        if ($shipHeading.length || $shipMethods.length) {
          var $shipWrap = $('<div class="fc-twocol-shipping"></div>');
          $shipWrap.append($shipHeading).append($shipMethods);
          $rightCol.append($shipWrap);
        }

        // 3. Metoda płatności (krok 3) — na dole
        $rightCol.append($form.find('.fc-checkout-step[data-step="3"]'));

        // 4. Przycisk „Złóż zamówienie" — na samym dole prawej kolumny
        var $submitActions = $rightCol.find(
          '.fc-checkout-step[data-step="4"] .fc-step-actions',
        );
        if ($submitActions.length) {
          $submitActions.addClass("fc-twocol-submit");
          $rightCol.append($submitActions);
        }

        $form.append($rightCol);
      }

      // Walidacja przy wysyłce formularza
      $(document).on("submit", ".fc-checkout-form", function (e) {
        var allValid = true;
        for (var s = 1; s <= 3; s++) {
          if (!fcValidateStep(s)) allValid = false;
        }
        if (!allValid) {
          e.preventDefault();
          var $firstErr = $(".fc-checkout-page .fc-field-invalid:first");
          if ($firstErr.length) {
            $("html, body").animate(
              { scrollTop: $firstErr.offset().top - 100 },
              300,
            );
            $firstErr.find("input, textarea, select").first().focus();
          }
        } else {
          fcPopulateSummary();
        }
      });

      // Wypełnij podsumowanie po załadowaniu
      setTimeout(fcPopulateSummary, 300);

      // Aktualizuj podsumowanie na bieżąco przy zmianach pól
      $(".fc-checkout-page").on(
        "change",
        'input[name="payment_method"], input[name="shipping_method"], input[name="ship_to_different"], input[name="account_type"], select',
        function () {
          fcPopulateSummary();
        },
      );
      $(".fc-checkout-page").on(
        "input",
        "#billing_first_name, #billing_last_name, #billing_company, #billing_address, #billing_postcode, #billing_city, #billing_email, #billing_phone, #billing_tax_no, #billing_crn, #shipping_first_name, #shipping_last_name, #shipping_company, #shipping_address, #shipping_postcode, #shipping_city",
        function () {
          fcPopulateSummary();
        },
      );
      return;
    }
    $(".fc-checkout-step")
      .not(".active")
      .find("[required]")
      .each(function () {
        $(this).attr("data-required", "1").removeAttr("required");
      });
  })();

  /* ===== Checkout: toggle formularza logowania ===== */
  $(document).on("click", ".fc-checkout-login-toggle", function (e) {
    e.preventDefault();
    var $form = $(".fc-checkout-login-form");
    var $notice = $(".fc-checkout-login-notice");
    var scrollTarget = $notice.length ? $notice : $form;

    // Rozwiń panel jeśli jest ukryty
    if (!$form.is(":visible")) {
      $form.slideDown(250, function () {
        $form.find("input:first").focus();
      });
    }

    // Scrolluj na samą górę strony
    $("html, body").animate({ scrollTop: 0 }, 350);
  });

  /* ===== Checkout: toggle rejestracji konta ===== */
  $(document).on(
    "change",
    '.fc-checkout-register-toggle input[type="checkbox"]',
    function () {
      var $fields = $(this)
        .closest(".fc-checkout-register")
        .find(".fc-checkout-register-fields");
      var $pwd = $fields.find("#fc_reg_password");
      var $pwd2 = $fields.find("#fc_reg_password2");
      if ($(this).is(":checked")) {
        $fields.slideDown(200);
        $pwd.attr("required", "required");
        $pwd2.attr("required", "required");
      } else {
        $fields.slideUp(200);
        $pwd.removeAttr("required").val("");
        $pwd2.removeAttr("required").val("");
      }
    },
  );

  /* ===== Selektor kierunkowego telefonu ===== */
  (function () {
    // Otwórz/zamknij dropdown
    $(document).on("click", ".fc-phone-prefix-btn", function (e) {
      e.stopPropagation();
      var $wrap = $(this).closest(".fc-phone-wrap");
      var $dd = $wrap.find(".fc-phone-dropdown");
      // Zamknij inne phone + country dropdowny
      $(".fc-phone-dropdown.open, .fc-country-dropdown.open")
        .not($dd)
        .removeClass("open");
      $dd.toggleClass("open");
      if ($dd.hasClass("open")) {
        $dd
          .find(".fc-phone-dropdown-search input")
          .val("")
          .trigger("input")
          .focus();
      }
    });

    // Zamknij przy kliknięciu poza
    $(document).on("click", function () {
      $(".fc-phone-dropdown.open, .fc-country-dropdown.open").removeClass(
        "open",
      );
    });
    $(document).on("click", ".fc-phone-dropdown", function (e) {
      e.stopPropagation();
    });

    // Wybór kraju
    $(document).on("click", ".fc-phone-dropdown-list li", function () {
      var $li = $(this);
      var $wrap = $li.closest(".fc-phone-wrap");
      var code = $li.data("code");
      var prefix = $li.data("prefix");
      var flag = $li.data("flag");

      $wrap.find(".fc-phone-prefix-btn .fc-flag").text(flag);
      $wrap.find(".fc-phone-prefix-btn .fc-prefix-code").text(prefix);
      $wrap.find(".fc-phone-prefix-btn").attr("data-current", code);
      $wrap.find(".fc-phone-prefix-value").val(prefix);
      $wrap.find(".fc-phone-dropdown-list li").removeClass("active");
      $li.addClass("active");
      $wrap.find(".fc-phone-dropdown").removeClass("open");
    });

    // Filtrowanie po nazwie/numerze
    $(document).on("input", ".fc-phone-dropdown-search input", function () {
      var q = $(this).val().toLowerCase();
      $(this)
        .closest(".fc-phone-dropdown")
        .find(".fc-phone-dropdown-list li")
        .each(function () {
          var name = ($(this).find(".fc-dl-name").text() || "").toLowerCase();
          var prefix = (
            $(this).find(".fc-dl-prefix").text() || ""
          ).toLowerCase();
          $(this).toggle(name.indexOf(q) !== -1 || prefix.indexOf(q) !== -1);
        });
    });

    // Auto-sync kierunkowego z billing_country (custom event)
    $(document).on("fc:countryChange", "#billing_country", function () {
      var countryCode = $(this).val();
      var $wrap = $(this).closest("form").find(".fc-phone-wrap");
      if (!$wrap.length) return;
      var $li = $wrap.find(
        '.fc-phone-dropdown-list li[data-code="' + countryCode + '"]',
      );
      if ($li.length) {
        $li.trigger("click");
      }
    });
  })();

  /* ===== Selektor kraju z flagą ===== */
  (function () {
    // Otwórz/zamknij dropdown
    $(document).on("click", ".fc-country-select-btn", function (e) {
      e.stopPropagation();
      var $wrap = $(this).closest(".fc-country-select-wrap");
      var $dd = $wrap.find(".fc-country-dropdown");
      // Zamknij inne country + phone dropdowny
      $(".fc-country-dropdown.open, .fc-phone-dropdown.open")
        .not($dd)
        .removeClass("open");
      $dd.toggleClass("open");
      if ($dd.hasClass("open")) {
        $dd
          .find(".fc-country-dropdown-search input")
          .val("")
          .trigger("input")
          .focus();
      }
    });

    // Zamknij przy kliknięciu poza
    $(document).on("click", function () {
      $(".fc-country-dropdown.open").removeClass("open");
    });
    $(document).on("click", ".fc-country-dropdown", function (e) {
      e.stopPropagation();
    });

    // Wybór kraju
    $(document).on("click", ".fc-country-dropdown-list li", function () {
      var $li = $(this);
      var $wrap = $li.closest(".fc-country-select-wrap");
      var code = $li.data("code");
      var flag = $li.data("flag");
      var name = $li.find(".fc-dl-name").text();

      $wrap.find(".fc-country-select-btn .fc-flag").text(flag);
      $wrap.find(".fc-country-select-btn .fc-country-label").text(name);
      var $hidden = $wrap.find('input[type="hidden"]');
      $hidden.val(code);
      $wrap.find(".fc-country-dropdown-list li").removeClass("active");
      $li.addClass("active");
      $wrap.find(".fc-country-dropdown").removeClass("open");

      // Triggeruj event change + custom event do sync kierunkowego
      $hidden.trigger("change").trigger("fc:countryChange");
    });

    // Filtrowanie
    $(document).on("input", ".fc-country-dropdown-search input", function () {
      var q = $(this).val().toLowerCase();
      $(this)
        .closest(".fc-country-dropdown")
        .find(".fc-country-dropdown-list li")
        .each(function () {
          var name = ($(this).find(".fc-dl-name").text() || "").toLowerCase();
          $(this).toggle(name.indexOf(q) !== -1);
        });
    });

    /* ── Price range slider ── */
    $(".fc-price-slider-wrap").each(function () {
      var $wrap = $(this),
        $min = $wrap.find(".fc-range-min"),
        $max = $wrap.find(".fc-range-max"),
        $range = $wrap.find(".fc-price-slider-range"),
        $labelMin = $wrap.find(".fc-price-slider-val-min"),
        $labelMax = $wrap.find(".fc-price-slider-val-max"),
        absMin = parseFloat($wrap.data("min")),
        absMax = parseFloat($wrap.data("max")),
        currency = $wrap.data("currency") || "";

      function update() {
        var lo = parseFloat($min.val()),
          hi = parseFloat($max.val());
        if (lo > hi) {
          var t = lo;
          lo = hi;
          hi = t;
          $min.val(lo);
          $max.val(hi);
        }
        var pct = absMax - absMin || 1;
        $range.css({
          left: ((lo - absMin) / pct) * 100 + "%",
          right: 100 - ((hi - absMin) / pct) * 100 + "%",
        });
        $labelMin.text(currency + " " + lo);
        $labelMax.text(currency + " " + hi);
      }
      $min.on("input", function () {
        if (parseFloat($min.val()) > parseFloat($max.val()))
          $min.val($max.val());
        update();
      });
      $max.on("input", function () {
        if (parseFloat($max.val()) < parseFloat($min.val()))
          $max.val($min.val());
        update();
      });
      update();
    });
  })();

  // ===== Custom attribute dropdown toggle =====
  $(document).on("click", ".fc-attr-dropdown-toggle", function (e) {
    e.stopPropagation();
    var $dd = $(this).closest(".fc-attr-dropdown");
    $(".fc-attr-dropdown.open, .fc-sort-dropdown.open")
      .not($dd)
      .removeClass("open");
    $dd.toggleClass("open");
  });
  $(document).on("keydown", ".fc-attr-dropdown-toggle", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      $(this).trigger("click");
    }
  });

  // ===== Sort dropdown toggle =====
  $(document).on("click", ".fc-sort-toggle", function (e) {
    e.stopPropagation();
    var $dd = $(this).closest(".fc-sort-dropdown");
    $(".fc-sort-dropdown.open, .fc-attr-dropdown.open")
      .not($dd)
      .removeClass("open");
    $dd.toggleClass("open");
  });

  $(document).on("click", function () {
    $(".fc-attr-dropdown.open, .fc-sort-dropdown.open").removeClass("open");
  });
  $(document).on(
    "click",
    ".fc-attr-dropdown-list, .fc-sort-list",
    function (e) {
      e.stopPropagation();
    },
  );
  // ===== Expose cart functions globally for external plugins =====
  window.fcUpdateCartCount = fcUpdateCartCount;
  window.fcUpdateMiniCart = fcUpdateMiniCart;
  window.fcOpenMiniCart = fcOpenMiniCart;
  window.fcCloseMiniCart = fcCloseMiniCart;
  window.fcRefreshMiniCart = fcRefreshMiniCart;

  // ===== Related Products (Upsell) Carousel =====
  (function () {
    var $wrap = $(".fc-related-products");
    if (!$wrap.length) return;

    var $track = $wrap.find(".fc-related-track");
    var $slides = $track.children(".fc-related-slide");
    var $prev = $wrap.find(".fc-related-prev");
    var $next = $wrap.find(".fc-related-next");
    var currentIndex = 0;

    function getVisibleCount() {
      var w = window.innerWidth;
      if (w <= 767) return 2;
      if (w <= 1024) return 3;
      return 4;
    }

    function getGap() {
      var w = window.innerWidth;
      if (w <= 400) return 8;
      if (w <= 767) return 12;
      if (w <= 1024) return 16;
      return 20;
    }

    function maxIndex() {
      var total = $slides.length;
      var visible = getVisibleCount();
      return Math.max(0, total - visible);
    }

    function updateArrows() {
      $prev
        .toggleClass("disabled", currentIndex <= 0)
        .prop("disabled", currentIndex <= 0);
      $next
        .toggleClass("disabled", currentIndex >= maxIndex())
        .prop("disabled", currentIndex >= maxIndex());
    }

    function slideTo(idx) {
      var max = maxIndex();
      if (idx < 0) idx = 0;
      if (idx > max) idx = max;
      currentIndex = idx;

      var wrapperW = $wrap.find(".fc-related-track-wrapper").width();
      var gap = getGap();
      var visible = getVisibleCount();
      var slideW = (wrapperW - gap * (visible - 1)) / visible;
      var offset = currentIndex * (slideW + gap);

      $track.css("transform", "translateX(-" + offset + "px)");
      updateArrows();
    }

    $prev.on("click", function () {
      slideTo(currentIndex - 1);
    });

    $next.on("click", function () {
      slideTo(currentIndex + 1);
    });

    // Touch / swipe support
    var touchStartX = 0;
    var touchDiffX = 0;
    $track[0].addEventListener(
      "touchstart",
      function (e) {
        touchStartX = e.touches[0].clientX;
        touchDiffX = 0;
      },
      { passive: true },
    );
    $track[0].addEventListener(
      "touchmove",
      function (e) {
        touchDiffX = e.touches[0].clientX - touchStartX;
      },
      { passive: true },
    );
    $track[0].addEventListener("touchend", function () {
      if (touchDiffX > 40) slideTo(currentIndex - 1);
      else if (touchDiffX < -40) slideTo(currentIndex + 1);
    });

    // Init & recalculate on resize
    var resizeTimer;
    $(window).on("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        slideTo(currentIndex);
      }, 150);
    });

    slideTo(0);
  })();
})(jQuery);
