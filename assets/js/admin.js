/**
 * Flavor Commerce — Admin JavaScript
 */
(function ($) {
  "use strict";

  function fcAdminToast(message, type) {
    type = type || "info";
    var $toast = $(
      '<div class="fc-admin-toast fc-admin-toast-' +
        type +
        '" role="alert" aria-live="assertive">' +
        "<span>" +
        $("<span>").text(message).html() +
        "</span>" +
        '<button type="button" class="fc-admin-toast-close" aria-label="' +
        (fc_admin_vars.i18n.close || "Close") +
        '">&times;</button>' +
        "</div>",
    );
    $("body").append($toast);
    setTimeout(function () {
      $toast.addClass("fc-admin-toast-visible");
    }, 10);
    var timer = setTimeout(function () {
      $toast.removeClass("fc-admin-toast-visible");
      setTimeout(function () {
        $toast.remove();
      }, 300);
    }, 5000);
    $toast.find(".fc-admin-toast-close").on("click", function () {
      clearTimeout(timer);
      $toast.removeClass("fc-admin-toast-visible");
      setTimeout(function () {
        $toast.remove();
      }, 300);
    });
  }

  // ===== Product Status Icon Toggle =====
  $(document).on("change", "#fc_post_status", function () {
    var val = $(this).val();
    var icons = {
      fc_published: "dashicons-visibility",
      fc_draft: "dashicons-edit",
      fc_hidden: "dashicons-hidden",
      fc_preorder: "dashicons-clock",
    };
    var icon = icons[val] || "dashicons-visibility";
    $("#fc_status_icon").attr("class", "dashicons " + icon);

    // Show/hide publish date field
    if (val === "fc_hidden" || val === "fc_preorder") {
      $("#fc_publish_date_wrap").slideDown(200);
    } else {
      $("#fc_publish_date_wrap").slideUp(200);
      $("#fc_publish_date").val("");
    }

    // Show/hide shipping date field
    if (val === "fc_preorder") {
      $("#fc_shipping_date_wrap").slideDown(200);
    } else {
      $("#fc_shipping_date_wrap").slideUp(200);
      $("#fc_shipping_date").val("");
    }
  });

  // Clear publish date
  $(document).on("click", "#fc_publish_date_clear", function () {
    $("#fc_publish_date").val("");
    $(this).remove();
  });

  // Clear shipping date
  $(document).on("click", "#fc_shipping_date_clear", function () {
    $("#fc_shipping_date").val("");
    $(this).remove();
  });

  // ===== Product Meta Tabs =====
  $(document).on("click", ".fc-tab-btn", function (e) {
    e.preventDefault();
    var tab = $(this).data("tab");

    $(".fc-tab-btn").removeClass("active");
    $(this).addClass("active");

    $(".fc-tab-content").removeClass("active");
    $('.fc-tab-content[data-tab="' + tab + '"]').addClass("active");
  });

  // ===== Manage Stock toggle =====
  $(document).on("change", 'input[name="fc_manage_stock"]', function () {
    if ($(this).is(":checked")) {
      $(".fc-stock-field").show();
    } else {
      $(".fc-stock-field").hide();
    }
  });

  // ===== Gallery: Add images =====
  $(document).on("click", ".fc-gallery-add", function (e) {
    e.preventDefault();

    var frame = wp.media({
      title: fc_admin_vars.i18n.media_select_images,
      multiple: true,
      library: { type: "image" },
    });

    frame.on("select", function () {
      var attachments = frame.state().get("selection").toJSON();
      var $container = $(".fc-gallery-images");
      var ids = [];

      // Zachowaj istniejące
      $container.find(".fc-gallery-item").each(function () {
        ids.push($(this).data("id"));
      });

      attachments.forEach(function (att) {
        if (ids.indexOf(att.id) === -1) {
          ids.push(att.id);
          var thumbUrl =
            att.sizes && att.sizes.thumbnail
              ? att.sizes.thumbnail.url
              : att.url;
          $container.append(
            '<div class="fc-gallery-item" data-id="' +
              att.id +
              '">' +
              '<img src="' +
              thumbUrl +
              '" alt="">' +
              '<button type="button" class="fc-gallery-remove">&times;</button>' +
              "</div>",
          );
        }
      });

      $('input[name="fc_gallery"]').val(ids.join(","));
    });

    frame.open();
  });

  // ===== Gallery: Remove image =====
  $(document).on("click", ".fc-gallery-remove", function (e) {
    e.preventDefault();
    $(this).closest(".fc-gallery-item").remove();

    var ids = [];
    $(".fc-gallery-item").each(function () {
      ids.push($(this).data("id"));
    });
    $('input[name="fc_gallery"]').val(ids.join(","));
  });

  // ===================================================================
  //  Custom Product Form — Media Handling
  // ===================================================================

  // ===== Thumbnail: Set =====
  $(document).on(
    "click",
    "#fc_set_thumbnail, .fc-thumbnail-preview",
    function (e) {
      e.preventDefault();

      var frame = wp.media({
        title: fc_admin_vars.i18n.media_select_main_image,
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var att = frame.state().get("selection").first().toJSON();
        var imgUrl =
          att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
        $("#product_thumbnail").val(att.id);
        $("#fc_thumbnail_preview").html('<img src="' + imgUrl + '" alt="">');
        $("#fc_remove_thumbnail").show();
      });

      frame.open();
    },
  );

  // ===== Thumbnail: Remove =====
  $(document).on("click", "#fc_remove_thumbnail", function (e) {
    e.preventDefault();
    $("#product_thumbnail").val("");
    $("#fc_thumbnail_preview").html(
      '<div class="fc-upload-placeholder">' +
        '<span class="dashicons dashicons-format-image"></span>' +
        "<p>" +
        fc_admin_vars.i18n.click_to_add_image +
        "</p>" +
        "</div>",
    );
    $(this).hide();
  });

  // ===== Gallery: Add images (custom form) =====
  $(document).on("click", "#fc_add_gallery", function (e) {
    e.preventDefault();

    var frame = wp.media({
      title: fc_admin_vars.i18n.media_add_to_gallery,
      multiple: true,
      library: { type: "image" },
    });

    frame.on("select", function () {
      var attachments = frame.state().get("selection").toJSON();
      var $grid = $("#fc_gallery_grid");
      var currentIds = $("#fc_gallery_input").val()
        ? $("#fc_gallery_input").val().split(",").map(Number)
        : [];

      attachments.forEach(function (att) {
        if (currentIds.indexOf(att.id) === -1) {
          currentIds.push(att.id);
          var thumbUrl =
            att.sizes && att.sizes.thumbnail
              ? att.sizes.thumbnail.url
              : att.url;
          $grid.append(
            '<div class="fc-gallery-thumb" data-id="' +
              att.id +
              '">' +
              '<img src="' +
              thumbUrl +
              '" alt="">' +
              '<button type="button" class="fc-gallery-remove-btn">&times;</button>' +
              "</div>",
          );
        }
      });

      $("#fc_gallery_input").val(currentIds.join(","));
    });

    frame.open();
  });

  // ===== Gallery: Remove image (custom form) =====
  $(document).on("click", ".fc-gallery-remove-btn", function (e) {
    e.preventDefault();
    $(this).closest(".fc-gallery-thumb").remove();

    var ids = [];
    $("#fc_gallery_grid .fc-gallery-thumb").each(function () {
      ids.push($(this).data("id"));
    });
    $("#fc_gallery_input").val(ids.join(","));
  });

  // ===== Manage Stock toggle (custom form) =====
  $(document).on("change", "#fc_manage_stock_cb", function () {
    if ($(this).is(":checked")) {
      $(".fc-stock-qty-field").show();
    } else {
      $(".fc-stock-qty-field").hide();
    }
  });

  // ===== Add Category: toggle form =====
  $(document).on("click", "#fc_add_cat_toggle", function (e) {
    e.preventDefault();
    $("#fc_add_cat_form").slideDown(200);
    $(this).attr("aria-expanded", "true").hide();
    $("#fc_new_cat_name").focus();
  });

  $(document).on("click", "#fc_add_cat_cancel", function () {
    $("#fc_add_cat_form").slideUp(200, function () {
      // Clear fields
      $("#fc_new_cat_name, #fc_new_cat_slug, #fc_new_cat_desc").val("");
      $("#fc_new_cat_parent").val("0");
    });
    $("#fc_add_cat_toggle").attr("aria-expanded", "false").show();
  });

  // ===== Add Category inline (AJAX) =====
  $(document).on("click", "#fc_add_category_btn", function () {
    var name = $("#fc_new_cat_name").val().trim();
    if (!name) {
      $("#fc_new_cat_name").focus();
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true).text(fc_admin_vars.i18n.adding_progress);

    $.post(
      ajaxurl,
      {
        action: "fc_admin_add_category",
        category_name: name,
        category_slug: $("#fc_new_cat_slug").val().trim(),
        category_description: $("#fc_new_cat_desc").val().trim(),
        category_parent: $("#fc_new_cat_parent").val(),
        _ajax_nonce: fc_admin_vars ? fc_admin_vars.nonce : "",
      },
      function (res) {
        $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_category);
        if (res.success) {
          var id = res.data.term_id;
          var catName = res.data.name;
          var parentId = res.data.parent || 0;
          var indent = parentId > 0 ? "fc-cat-child" : "";
          var displayName = fcEscHtml(catName);
          if (res.data.parent_name) {
            displayName =
              fcEscHtml(res.data.parent_name) + " → " + fcEscHtml(catName);
          }
          $(".fc-categories-list").append(
            '<label class="fc-category-item ' +
              indent +
              '">' +
              '<input type="checkbox" name="product_categories[]" value="' +
              id +
              '" checked> ' +
              displayName +
              "</label>",
          );
          // Add to parent dropdown
          $("#fc_new_cat_parent").append(
            '<option value="' + id + '">' + fcEscHtml(catName) + "</option>",
          );
          // Clear and close
          $("#fc_new_cat_name, #fc_new_cat_slug, #fc_new_cat_desc").val("");
          $("#fc_new_cat_parent").val("0");
          $("#fc_add_cat_form").slideUp(200);
          $("#fc_add_cat_toggle").show();
          fcUpdateCheckedCount($(".fc-categories-list"), $("#fc_cat_count"));
        } else {
          fcAdminToast(
            res.data || fc_admin_vars.i18n.error_adding_category,
            "error",
          );
        }
      },
    ).fail(function () {
      $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_category);
      fcAdminToast(fc_admin_vars.i18n.connection_error, "error");
    });
  });

  // ===== Add Brand: toggle form =====
  $(document).on("click", "#fc_add_brand_toggle", function (e) {
    e.preventDefault();
    $("#fc_add_brand_form").slideDown(200);
    $(this).attr("aria-expanded", "true").hide();
    $("#fc_new_brand_name").focus();
  });

  $(document).on("click", "#fc_add_brand_cancel", function () {
    $("#fc_add_brand_form").slideUp(200, function () {
      $("#fc_new_brand_name, #fc_new_brand_slug, #fc_new_brand_desc").val("");
    });
    $("#fc_add_brand_toggle").attr("aria-expanded", "false").show();
  });

  // ===== Add Brand (AJAX) =====
  $(document).on("click", "#fc_add_brand_btn", function () {
    var name = $("#fc_new_brand_name").val().trim();
    if (!name) {
      $("#fc_new_brand_name").focus();
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true).text(fc_admin_vars.i18n.adding_progress);

    $.post(
      ajaxurl,
      {
        action: "fc_admin_add_brand",
        brand_name: name,
        brand_slug: $("#fc_new_brand_slug").val().trim(),
        brand_description: $("#fc_new_brand_desc").val().trim(),
        brand_logo: $("#fc_new_brand_logo").val() || "",
        _ajax_nonce:
          typeof fc_admin_vars !== "undefined" ? fc_admin_vars.nonce : "",
      },
      function (res) {
        $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_brand);
        if (res.success) {
          var id = res.data.term_id;
          var brandName = res.data.name;
          // Add to select and auto-select
          $("#fc_product_brand").append(
            '<option value="' + id + '">' + fcEscHtml(brandName) + "</option>",
          );
          $("#fc_product_brand").val(id);
          // Clear and close form
          $("#fc_new_brand_name, #fc_new_brand_slug, #fc_new_brand_desc").val(
            "",
          );
          $("#fc_new_brand_logo").val("");
          $("#fc_new_brand_logo_preview").empty().hide();
          $("#fc_new_brand_logo_remove").hide();
          $("#fc_add_brand_form").slideUp(200);
          $("#fc_add_brand_toggle").show();
        } else {
          fcAdminToast(
            res.data || fc_admin_vars.i18n.error_adding_brand,
            "error",
          );
        }
      },
    ).fail(function () {
      $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_brand);
      fcAdminToast(fc_admin_vars.i18n.connection_error, "error");
    });
  });

  // ===== Add Unit: toggle form =====
  $(document).on("click", "#fc_add_unit_toggle", function (e) {
    e.preventDefault();
    $("#fc_add_unit_form").slideDown(200);
    $(this).attr("aria-expanded", "true").hide();
    $("#fc_new_unit_name").focus();
  });

  $(document).on("click", "#fc_add_unit_cancel", function () {
    $("#fc_add_unit_form").slideUp(200, function () {
      $("#fc_new_unit_name").val("");
    });
    $("#fc_add_unit_toggle").attr("aria-expanded", "false").show();
  });

  // ===== Add Unit (AJAX) =====
  $(document).on("click", "#fc_add_unit_btn", function () {
    var name = $("#fc_new_unit_name").val().trim();
    if (!name) {
      $("#fc_new_unit_name").focus();
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true).text(fc_admin_vars.i18n.adding_progress);

    $.post(
      ajaxurl,
      {
        action: "fc_admin_add_unit",
        unit_name: name,
        _ajax_nonce:
          typeof fc_admin_vars !== "undefined" ? fc_admin_vars.nonce : "",
      },
      function (res) {
        $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_unit);
        if (res.success) {
          var unitName = res.data.name;
          $("#fc_unit").append(
            '<option value="' +
              $("<span>").text(unitName).html() +
              '">' +
              $("<span>").text(unitName).html() +
              "</option>",
          );
          $("#fc_unit").val(unitName);
          $("#fc_new_unit_name").val("");
          $("#fc_add_unit_form").slideUp(200);
          $("#fc_add_unit_toggle").show();
        } else {
          fcAdminToast(
            res.data || fc_admin_vars.i18n.error_adding_unit,
            "error",
          );
        }
      },
    ).fail(function () {
      $btn.prop("disabled", false).text(fc_admin_vars.i18n.add_unit);
      fcAdminToast(fc_admin_vars.i18n.connection_error, "error");
    });
  });

  // ===================================================================
  //  Product Type Switching
  // ===================================================================

  function fcSwitchProductType(type) {
    // Highlight active type
    $(".fc-type-option").removeClass("active");
    $('input[name="fc_product_type"][value="' + type + '"]')
      .closest(".fc-type-option")
      .addClass("active");

    // Hide all conditional sections
    $(".fc-section-simple, .fc-section-variable, .fc-section-digital").hide();

    // Show sections matching this type
    $(".fc-section-" + type).show();

    // Toggle required on variant price inputs to prevent hidden required blocking submit
    if (type === "variable") {
      $(".fc-v-price").attr("required", true);
    } else {
      $(".fc-v-price").removeAttr("required");
    }
  }

  // Track previous product type
  var fcPrevProductType =
    $('input[name="fc_product_type"]:checked').val() || "simple";

  // Product type radio change
  $(document).on("change", 'input[name="fc_product_type"]', function () {
    var newType = $(this).val();

    // Switching away from variable — clear variants data
    var hadVariantData =
      fcPrevProductType === "variable" &&
      (fcAttributes.length > 0 || $("#fc_combinations_body tr").length > 0);

    if (hadVariantData && newType !== "variable") {
      if (!confirm(fc_admin_vars.i18n.confirm_type_change_lose)) {
        $(
          'input[name="fc_product_type"][value="' + fcPrevProductType + '"]',
        ).prop("checked", true);
        return;
      }
      fcAttributes = [];
      fcSyncAttributesJSON();
      fcRenderAttributeRows();
      $("#fc_attr_names_input").val("");
      $("#fc_attr_config").hide();
      $("#fc_combinations_body").empty();
      fcVariants = [];
      fcSyncVariantsJSON();
      $("#fc_combinations_table")
        .closest(".fc-form-card")
        .find(".fc-comb-global-toggle")
        .remove();
    }

    // Switching away from simple/digital to variable — clear simple fields
    var hadSimpleData =
      fcPrevProductType !== "variable" &&
      ($("#fc_price").val() !== "" ||
        $("#fc_sale_price").val() !== "" ||
        $("#fc_sku").val() !== "" ||
        $("#fc_stock").val() !== "" ||
        $("#fc_weight").val() !== "");

    if (hadSimpleData && newType === "variable") {
      if (!confirm(fc_admin_vars.i18n.confirm_type_change_var)) {
        $(
          'input[name="fc_product_type"][value="' + fcPrevProductType + '"]',
        ).prop("checked", true);
        return;
      }
      $("#fc_price").val("");
      $("#fc_sale_price").val("");
      $("#fc_sku").val("");
      $("#fc_stock").val("");
      $("#fc_weight").val("");
    }

    fcPrevProductType = newType;
    fcSwitchProductType(newType);
  });

  // ===================================================================
  //  Attribute-based Variants Management
  // ===================================================================

  // Runtime state for attributes
  var fcAttributes = [];
  try {
    var raw = $("#fc_attributes_json").val();
    if (raw) fcAttributes = JSON.parse(raw);
  } catch (e) {
    fcAttributes = [];
  }

  function fcSyncAttributesJSON() {
    $("#fc_attributes_json").val(JSON.stringify(fcAttributes));
  }

  // --- Add attribute button ---
  $(document).on("click", "#fc_add_attr_btn", function () {
    // Try auto-fill from global attributes
    fcLoadGlobalAttrs(function () {
      fcAttributes.push({ name: "", type: "text", values: [] });
      fcSyncAttributesJSON();
      fcRenderAttributeRows();
      // Focus the new name input
      setTimeout(function () {
        $("#fc_attr_list .fc-attr-row:last-child .fc-attr-name-input").focus();
      }, 50);
    });
  });

  // --- Render attribute rows (tag-input style) ---
  function fcRenderAttributeRows() {
    var $list = $("#fc_attr_list");
    $list.empty();

    fcAttributes.forEach(function (attr, idx) {
      // Build tags HTML
      var tagsHtml = "";
      if (attr.values && attr.values.length) {
        attr.values.forEach(function (val, vi) {
          var label = typeof val === "object" ? val.label : val;
          var extra = "";
          if (attr.type === "color") {
            var cval = val.value || "#000000";
            extra =
              '<input type="color" class="fc-tag-color-picker" data-attr-index="' +
              idx +
              '" data-val-index="' +
              vi +
              '" value="' +
              fcEscAttr(cval) +
              '" title="' +
              fc_admin_vars.i18n.click_to_change_color +
              '">';
          } else if (attr.type === "image") {
            var imgUrl = val.url || "";
            extra = imgUrl
              ? '<img src="' +
                fcEscAttr(imgUrl) +
                '" class="fc-tag-image-preview" data-attr-index="' +
                idx +
                '" data-val-index="' +
                vi +
                '" title="' +
                fc_admin_vars.i18n.click_to_change +
                '">'
              : '<button type="button" class="fc-tag-image-pick" data-attr-index="' +
                idx +
                '" data-val-index="' +
                vi +
                '" title="' +
                fc_admin_vars.i18n.select_image +
                '">🖼</button>';
          }
          tagsHtml +=
            '<span class="fc-attr-tag" data-attr-index="' +
            idx +
            '" data-val-index="' +
            vi +
            '">' +
            extra +
            '<span class="fc-attr-tag-label">' +
            fcEscHtml(label) +
            "</span>" +
            '<button type="button" class="fc-attr-tag-remove" data-attr-index="' +
            idx +
            '" data-val-index="' +
            vi +
            '">&times;</button>' +
            "</span>";
        });
      }

      // Global attr suggestions for this attribute
      var suggestionsHtml = "";
      if (fcGlobalAttrsLoaded && attr.name) {
        var gMatch = null;
        for (var g = 0; g < fcGlobalAttrs.length; g++) {
          if (fcGlobalAttrs[g].name.toLowerCase() === attr.name.toLowerCase()) {
            gMatch = fcGlobalAttrs[g];
            break;
          }
        }
        if (gMatch && gMatch.values && gMatch.values.length) {
          var existingLabels = (attr.values || []).map(function (v) {
            return (typeof v === "object" ? v.label : v).toLowerCase();
          });
          var missing = gMatch.values.filter(function (gv) {
            var lbl = (typeof gv === "object" ? gv.label : gv).toLowerCase();
            return existingLabels.indexOf(lbl) === -1;
          });
          if (missing.length) {
            suggestionsHtml =
              '<div class="fc-attr-suggestions-inline" data-attr-index="' +
              idx +
              '">' +
              '<span class="fc-sugg-label">' +
              fc_admin_vars.i18n.available +
              "</span> ";
            missing.forEach(function (sv) {
              var lbl = typeof sv === "object" ? sv.label : sv;
              suggestionsHtml +=
                '<button type="button" class="fc-attr-sugg-add" data-attr-index="' +
                idx +
                "\" data-value='" +
                fcEscAttr(JSON.stringify(sv)) +
                "'>+ " +
                fcEscHtml(lbl) +
                "</button> ";
            });
            suggestionsHtml += "</div>";
          }
        }
      }

      var html =
        '<div class="fc-attr-row" data-index="' +
        idx +
        '">' +
        '<div class="fc-attr-row-header">' +
        '<input type="text" class="fc-attr-name-input" data-attr-index="' +
        idx +
        '" value="' +
        fcEscAttr(attr.name) +
        '" placeholder="' +
        fc_admin_vars.i18n.attr_name_placeholder +
        '">' +
        '<select class="fc-attr-type-select" data-attr-index="' +
        idx +
        '">' +
        '<option value="text"' +
        (attr.type === "text" ? " selected" : "") +
        ">" +
        fc_admin_vars.i18n.type_text +
        "</option>" +
        '<option value="color"' +
        (attr.type === "color" ? " selected" : "") +
        ">" +
        fc_admin_vars.i18n.type_color +
        "</option>" +
        '<option value="image"' +
        (attr.type === "image" ? " selected" : "") +
        ">" +
        fc_admin_vars.i18n.type_image +
        "</option>" +
        "</select>" +
        '<button type="button" class="fc-attr-remove" title="' +
        fc_admin_vars.i18n.remove_attribute +
        '">&times;</button>' +
        "</div>" +
        '<div class="fc-attr-tags-wrap">' +
        tagsHtml +
        '<input type="text" class="fc-attr-tag-input" data-attr-index="' +
        idx +
        '" placeholder="' +
        fc_admin_vars.i18n.type_value_enter +
        '">' +
        "</div>" +
        suggestionsHtml +
        "</div>";

      $list.append(html);
    });

    // Auto-merge after rendering (skip on initial load)
    if (!fcAttrInitialRender) {
      fcSmartMerge();
    }
    fcAttrInitialRender = false;
  }

  var fcAttrInitialRender = true;

  // --- Smart merge: auto-generate variants from attributes ---
  function fcSmartMerge() {
    var attrValueArrays = [];
    var attrNames = [];

    for (var i = 0; i < fcAttributes.length; i++) {
      if (
        !fcAttributes[i].name ||
        !fcAttributes[i].values ||
        !fcAttributes[i].values.length
      ) {
        continue;
      }
      attrNames.push(fcAttributes[i].name);
      attrValueArrays.push(fcAttributes[i].values);
    }

    if (!attrNames.length) {
      if (fcVariants.length) {
        fcVariants = [];
        fcSyncVariantsJSON();
        fcRenderVariantsTable();
      }
      return;
    }

    var combinations = fcCartesian(attrValueArrays);

    // Build map of existing variants by hash
    var existingMap = {};
    fcVariants.forEach(function (v) {
      var hash = fcVariantHash(v.attribute_values);
      existingMap[hash] = v;
    });

    var newVariants = [];
    combinations.forEach(function (combo) {
      var nameparts = [];
      var attrVals = {};
      combo.forEach(function (val, vi) {
        var label = typeof val === "object" ? val.label : val;
        nameparts.push(attrNames[vi] + ": " + label);
        attrVals[attrNames[vi]] = label;
      });

      var hash = fcVariantHash(attrVals);
      var existing = existingMap[hash];

      if (existing) {
        existing.id = hash;
        existing.name = nameparts.join(" / ");
        existing.attribute_values = attrVals;
        newVariants.push(existing);
      } else {
        newVariants.push({
          id: hash,
          name: nameparts.join(" / "),
          attribute_values: attrVals,
          sku: "",
          price: "",
          sale_price: "",
          stock: "",
          images: [],
          main_image: 0,
          status: "active",
        });
      }
    });

    fcVariants = newVariants;
    fcSyncVariantsJSON();
    fcRenderVariantsTable();
  }

  // --- Variant hash: deterministic ID from attribute values ---
  function fcVariantHash(attrVals) {
    if (!attrVals || typeof attrVals !== "object") return "";
    var keys = Object.keys(attrVals).sort();
    var str = keys
      .map(function (k) {
        return k + ":" + attrVals[k];
      })
      .join("|");
    // djb2 hash → base36
    var hash = 5381;
    for (var i = 0; i < str.length; i++) {
      hash = (hash * 33) ^ str.charCodeAt(i);
    }
    return Math.abs(hash).toString(36);
  }

  // ===== Persistent selection + floating badge on WP list tables =====
  (function () {
    var $table = $("table.wp-list-table");
    if (!$table.length) return;

    var storageKey = "fc_selected_" + (window.typenow || "posts");
    var pageKey = storageKey + "_page";
    var $badge = $('<div class="fc-selection-badge"></div>').appendTo("body");
    var $resetBtn = $(
      '<button type="button" class="fc-selection-reset">' +
        fc_admin_vars.i18n.reset_selection +
        " &times;</button>",
    ).appendTo("body");

    // Detect if we're on the same list page type
    // Current page identifier: post_type list screen
    var currentPage =
      window.location.pathname + "?post_type=" + (window.typenow || "");

    // If the stored page doesn't match current list page, clear selections
    var storedPage = sessionStorage.getItem(pageKey) || "";
    if (storedPage && storedPage !== currentPage) {
      sessionStorage.removeItem(storageKey);
    }
    sessionStorage.setItem(pageKey, currentPage);

    // Load persisted selections
    function fcGetStored() {
      try {
        var data = sessionStorage.getItem(storageKey);
        return data ? JSON.parse(data) : [];
      } catch (e) {
        return [];
      }
    }

    function fcSetStored(ids) {
      sessionStorage.setItem(storageKey, JSON.stringify(ids));
    }

    var fcSelectedIds = fcGetStored();

    // On page load — restore checkboxes for IDs on this page
    $table.find('tbody input[id^="cb-select-"]').each(function () {
      var id = $(this).val();
      if (fcSelectedIds.indexOf(id) > -1) {
        $(this).prop("checked", true);
      }
    });

    function fcSyncFromCheckboxes() {
      $table.find('tbody input[id^="cb-select-"]').each(function () {
        var id = $(this).val();
        var idx = fcSelectedIds.indexOf(id);
        if ($(this).is(":checked")) {
          if (idx === -1) fcSelectedIds.push(id);
        } else {
          if (idx > -1) fcSelectedIds.splice(idx, 1);
        }
      });
      fcSetStored(fcSelectedIds);
    }

    function fcUpdateBadge() {
      var count = fcSelectedIds.length;
      if (count > 0) {
        $badge
          .text(fc_admin_vars.i18n.selected_count + count)
          .addClass("visible");
        $resetBtn.addClass("visible");
      } else {
        $badge.removeClass("visible");
        $resetBtn.removeClass("visible");
      }
    }

    // Initial badge state
    fcUpdateBadge();

    $(document).on("mousemove", function (e) {
      $badge.css({ left: e.clientX + 15, top: e.clientY + 15 });
    });

    // Individual checkbox change
    $table.on("change", 'tbody input[id^="cb-select-"]', function () {
      fcSyncFromCheckboxes();
      fcUpdateBadge();
    });

    // "Select all" checkbox
    $table.on(
      "change",
      'thead input[id="cb-select-all-1"], tfoot input[id="cb-select-all-2"]',
      function () {
        setTimeout(function () {
          fcSyncFromCheckboxes();
          fcUpdateBadge();
        }, 10);
      },
    );

    // Before bulk action submit — inject hidden inputs for off-page selections
    $("form#posts-filter").on("submit", function () {
      $(this).find(".fc-injected-cb").remove();

      var onPageIds = [];
      $table.find('tbody input[id^="cb-select-"]').each(function () {
        onPageIds.push($(this).val());
      });

      var $form = $(this);
      fcSelectedIds.forEach(function (id) {
        if (onPageIds.indexOf(id) === -1) {
          $form.append(
            '<input type="hidden" name="post[]" value="' +
              id +
              '" class="fc-injected-cb">',
          );
        }
      });

      sessionStorage.removeItem(storageKey);
    });

    // Reset button inside badge
    $(document).on("click", ".fc-selection-reset", function (e) {
      e.preventDefault();
      e.stopPropagation();
      fcSelectedIds = [];
      fcSetStored(fcSelectedIds);
      $table.find('tbody input[type="checkbox"]').prop("checked", false);
      $table
        .find('thead input[type="checkbox"], tfoot input[type="checkbox"]')
        .prop("checked", false);
      fcUpdateBadge();
    });

    // Clear storage when navigating away from list pages
    $(window).on("beforeunload", function () {
      // Check if destination is still the same list — we can't know,
      // so we rely on the page check at load time above
    });
  })();

  // ===== Checked count for checkbox lists =====
  function fcUpdateCheckedCount($list, $counter) {
    var count = $list.find('input[type="checkbox"]:checked').length;
    $counter.text(count > 0 ? "(" + count + ")" : "");
  }

  // Categories count
  $(document).on(
    "change",
    '.fc-categories-list input[type="checkbox"]',
    function () {
      fcUpdateCheckedCount($(".fc-categories-list"), $("#fc_cat_count"));
    },
  );

  // Remove entire attribute
  $(document).on("click", ".fc-attr-remove", function () {
    var $row = $(this).closest(".fc-attr-row");
    var idx = parseInt($row.data("index"));
    if (isNaN(idx) || !fcAttributes[idx]) return;
    var name = fcAttributes[idx].name || "";
    if (
      !confirm(fc_admin_vars.i18n.confirm_remove_attribute.replace("%s", name))
    )
      return;
    fcAttributes.splice(idx, 1);
    fcSyncAttributesJSON();
    fcRenderAttributeRows();
  });

  // Attribute name change
  $(document).on("change", ".fc-attr-name-input", function () {
    var idx = parseInt($(this).data("attr-index"));
    if (isNaN(idx) || !fcAttributes[idx]) return;
    var newName = $(this).val().trim();
    fcAttributes[idx].name = newName;
    fcSyncAttributesJSON();
    // Try to fill from global attrs
    if (newName && fcGlobalAttrsLoaded) {
      for (var g = 0; g < fcGlobalAttrs.length; g++) {
        if (fcGlobalAttrs[g].name.toLowerCase() === newName.toLowerCase()) {
          if (!fcAttributes[idx].values.length) {
            fcAttributes[idx].type = fcGlobalAttrs[g].type;
            fcAttributes[idx].values = JSON.parse(
              JSON.stringify(fcGlobalAttrs[g].values),
            );
            fcSyncAttributesJSON();
            fcRenderAttributeRows();
          }
          break;
        }
      }
    }
    fcSmartMerge();
  });

  // Attribute type change
  $(document).on("change", ".fc-attr-type-select", function () {
    var idx = parseInt($(this).data("attr-index"));
    var newType = $(this).val();
    // Convert existing values to new type format
    var oldValues = fcAttributes[idx].values || [];
    fcAttributes[idx].type = newType;
    fcAttributes[idx].values = oldValues.map(function (v) {
      var label = typeof v === "object" ? v.label : v;
      if (newType === "color")
        return { label: label, value: v.value || "#000000" };
      if (newType === "image")
        return { label: label, id: v.id || 0, url: v.url || "" };
      return { label: label };
    });
    fcSyncAttributesJSON();
    fcRenderAttributeRows();
  });

  // Tag input — add value on Enter
  $(document).on("keydown", ".fc-attr-tag-input", function (e) {
    if (e.key !== "Enter") return;
    e.preventDefault();
    var idx = parseInt($(this).data("attr-index"));
    var val = $(this).val().trim();
    if (!val || isNaN(idx) || !fcAttributes[idx]) return;

    // Check for duplicates
    var exists = fcAttributes[idx].values.some(function (v) {
      var label = typeof v === "object" ? v.label : v;
      return label.toLowerCase() === val.toLowerCase();
    });
    if (exists) {
      $(this).val("");
      return;
    }

    var newVal;
    if (fcAttributes[idx].type === "color") {
      newVal = { label: val, value: "#000000" };
    } else if (fcAttributes[idx].type === "image") {
      newVal = { label: val, id: 0, url: "" };
    } else {
      newVal = { label: val };
    }
    fcAttributes[idx].values.push(newVal);
    $(this).val("");
    fcSyncAttributesJSON();
    fcRenderAttributeRows();
  });

  // Tag remove
  $(document).on("click", ".fc-attr-tag-remove", function (e) {
    e.stopPropagation();
    var attrIdx = parseInt($(this).data("attr-index"));
    var valIdx = parseInt($(this).data("val-index"));
    if (isNaN(attrIdx) || !fcAttributes[attrIdx]) return;
    fcAttributes[attrIdx].values.splice(valIdx, 1);
    fcSyncAttributesJSON();
    fcRenderAttributeRows();
  });

  // Tag color picker change
  $(document).on("input change", ".fc-tag-color-picker", function () {
    var attrIdx = parseInt($(this).data("attr-index"));
    var valIdx = parseInt($(this).data("val-index"));
    if (isNaN(attrIdx) || !fcAttributes[attrIdx]) return;
    fcAttributes[attrIdx].values[valIdx].value = $(this).val();
    fcSyncAttributesJSON();
  });

  // Tag image picker — click preview or pick button
  $(document).on(
    "click",
    ".fc-tag-image-preview, .fc-tag-image-pick",
    function () {
      var attrIdx = parseInt($(this).data("attr-index"));
      var valIdx = parseInt($(this).data("val-index"));
      var frame = wp.media({
        title:
          fc_admin_vars.i18n.media_select_image_for +
          fcAttributes[attrIdx].values[valIdx].label,
        multiple: false,
        library: { type: "image" },
      });
      frame.on("select", function () {
        var att = frame.state().get("selection").first().toJSON();
        var thumbUrl =
          att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
        fcAttributes[attrIdx].values[valIdx].id = att.id;
        fcAttributes[attrIdx].values[valIdx].url = thumbUrl;
        fcSyncAttributesJSON();
        fcRenderAttributeRows();
      });
      frame.open();
    },
  );

  // Add suggestion value to attribute
  $(document).on("click", ".fc-attr-sugg-add", function () {
    var idx = parseInt($(this).data("attr-index"));
    var val = JSON.parse($(this).attr("data-value"));
    if (isNaN(idx) || !fcAttributes[idx]) return;
    fcAttributes[idx].values.push(val);
    fcSyncAttributesJSON();
    fcRenderAttributeRows();
  });

  // ===================================================================
  //  Variants — JSON-based (single hidden field, no per-row inputs)
  // ===================================================================

  // Runtime state
  var fcVariants = [];
  try {
    var rawV = $("#fc_variants_json").val();
    if (rawV) fcVariants = JSON.parse(rawV);
  } catch (e) {
    fcVariants = [];
  }

  function fcSyncVariantsJSON() {
    $("#fc_variants_json").val(JSON.stringify(fcVariants));
  }

  // Render existing variants on page load
  if (fcVariants.length > 0) {
    fcRenderVariantsTable();
  }

  // Initial render of existing attributes (after fcVariants is ready)
  if (fcAttributes.length) {
    fcLoadGlobalAttrs(function () {
      fcRenderAttributeRows();
    });
  }

  // Render the whole table from fcVariants array
  function fcRenderVariantsTable() {
    var $tbody = $("#fc_combinations_body");
    $tbody.empty();

    if (!fcVariants.length) {
      $("#fc_combinations_wrap").hide();
      return;
    }

    fcVariants.forEach(function (v, ci) {
      var imagesHtml = "";
      var mainImg = v.main_image || 0;
      if (v.images && v.images.length) {
        v.images.forEach(function (imgId) {
          var imgUrl = fcVariantImageUrl(imgId);
          if (!imgUrl) return;
          var isMain = parseInt(imgId) === parseInt(mainImg);
          imagesHtml +=
            '<div class="fc-comb-img-item' +
            (isMain ? " fc-comb-img-main" : "") +
            '" data-id="' +
            imgId +
            '" title="' +
            fc_admin_vars.i18n.click_to_set_as_main +
            '">' +
            '<img src="' +
            imgUrl +
            '" alt="">' +
            '<span class="fc-comb-img-main-badge">&#9733;</span>' +
            '<button type="button" class="fc-comb-img-remove">&times;</button>' +
            "</div>";
        });
      }

      var html =
        '<tr class="fc-combination-row" data-index="' +
        ci +
        '">' +
        "<td><strong>" +
        fcEscHtml(v.name) +
        "</strong></td>" +
        '<td><input type="text" class="fc-comb-input fc-v-sku" data-vi="' +
        ci +
        '" value="' +
        fcEscAttr(v.sku || "") +
        '" placeholder="SKU"></td>' +
        '<td><input type="number" class="fc-comb-input fc-comb-price fc-v-price" data-vi="' +
        ci +
        '" value="' +
        fcEscAttr(v.price || "") +
        '" step="0.01" min="0"></td>' +
        '<td><input type="text" class="fc-comb-input fc-comb-price fc-sale-input fc-v-sale" data-vi="' +
        ci +
        '" value="' +
        fcEscAttr(v.sale_price || "") +
        '" placeholder="0.00 lub %"></td>' +
        '<td><input type="number" class="fc-comb-input fc-v-stock" data-vi="' +
        ci +
        '" value="' +
        fcEscAttr(v.stock || "") +
        '" min="0" step="1" placeholder="∞"></td>' +
        "<td>" +
        '<div class="fc-comb-images-wrap" data-index="' +
        ci +
        '">' +
        '<div class="fc-comb-images-list">' +
        imagesHtml +
        "</div>" +
        '<button type="button" class="button button-small fc-comb-images-add">' +
        fc_admin_vars.i18n.add_photos +
        "</button>" +
        "</div>" +
        "</td>" +
        "<td>" +
        '<select class="fc-comb-input fc-v-status" data-vi="' +
        ci +
        '">' +
        '<option value="active"' +
        (v.status !== "inactive" ? " selected" : "") +
        ">" +
        fc_admin_vars.i18n.status_active +
        "</option>" +
        '<option value="inactive"' +
        (v.status === "inactive" ? " selected" : "") +
        ">" +
        fc_admin_vars.i18n.status_inactive +
        "</option>" +
        "</select>" +
        "</td>" +
        '<td><button type="button" class="fc-comb-remove" title="' +
        fc_admin_vars.i18n.remove +
        '">&times;</button></td>' +
        "</tr>";

      $tbody.append(html);
    });

    $("#fc_combinations_wrap").show();
  }

  // Helper: get thumbnail URL for image ID from gallery/featured
  function fcVariantImageUrl(imgId) {
    imgId = parseInt(imgId);
    // Try #fc_variant_thumbs data (preloaded from PHP)
    var thumbsMap = window.fcVariantThumbs || {};
    if (thumbsMap[imgId]) return thumbsMap[imgId];
    // Try gallery
    var $thumb = $(
      '#fc_gallery_grid .fc-gallery-thumb[data-id="' + imgId + '"] img',
    );
    if ($thumb.length) return $thumb.attr("src");
    // Try featured
    var featId = parseInt($("#product_thumbnail").val()) || 0;
    if (imgId === featId) {
      var $featImg = $("#fc_thumbnail_preview img");
      if ($featImg.length) return $featImg.attr("src");
    }
    return "";
  }

  // On page load — render table from loaded variants
  if (fcVariants.length) {
    fcRenderVariantsTable();
  }

  // --- Build image thumbs map for existing variant images ---
  // (will be populated from PHP via wp_localize_script)

  // --- Sync field changes to fcVariants array ---
  $(document).on("change input", ".fc-v-sku", function () {
    var i = parseInt($(this).data("vi"));
    if (fcVariants[i]) {
      fcVariants[i].sku = $(this).val();
      fcSyncVariantsJSON();
    }
  });
  $(document).on("change input", ".fc-v-price", function () {
    var i = parseInt($(this).data("vi"));
    if (fcVariants[i]) {
      fcVariants[i].price = $(this).val();
      fcSyncVariantsJSON();
    }
  });
  $(document).on("change input", ".fc-v-sale", function () {
    var i = parseInt($(this).data("vi"));
    if (fcVariants[i]) {
      fcVariants[i].sale_price = $(this).val();
      fcSyncVariantsJSON();
    }
  });
  $(document).on("change input", ".fc-v-stock", function () {
    var i = parseInt($(this).data("vi"));
    if (fcVariants[i]) {
      fcVariants[i].stock = $(this).val();
      fcSyncVariantsJSON();
    }
  });
  $(document).on("change", ".fc-v-status", function () {
    var i = parseInt($(this).data("vi"));
    if (fcVariants[i]) {
      fcVariants[i].status = $(this).val();
      fcSyncVariantsJSON();
    }
  });

  // --- Global combination options ---
  $(document).on(
    "click",
    "#fc_comb_global_toggle, .fc-comb-global-header",
    function (e) {
      // Prevent double-fire when clicking the button inside header
      if (
        $(e.target).closest("#fc_comb_global_toggle").length &&
        !$(e.currentTarget).is("#fc_comb_global_toggle")
      )
        return;
      var $body = $("#fc_comb_global_body");
      $body.slideToggle(200);
      $("#fc_comb_global_toggle .dashicons").toggleClass(
        "dashicons-arrow-down-alt2 dashicons-arrow-up-alt2",
      );
    },
  );

  $(document).on("click", ".fc-comb-global-apply", function () {
    var target = $(this).data("target");
    var val;
    if (target === "price") {
      val = $("#fc_comb_global_price").val();
      fcVariants.forEach(function (v) {
        v.price = val;
      });
    } else if (target === "sale_price") {
      val = $("#fc_comb_global_sale_price").val().trim();
      fcVariants.forEach(function (v) {
        var pctMatch = val.match(/^-?(\d+(?:[.,]\d+)?)\s*%$/);
        if (pctMatch) {
          var pct = parseFloat(pctMatch[1].replace(",", "."));
          var regPrice = parseFloat(v.price) || 0;
          if (regPrice > 0 && pct > 0 && pct < 100) {
            v.sale_price = (regPrice * (1 - pct / 100)).toFixed(2);
          }
        } else {
          v.sale_price = val;
        }
      });
    } else if (target === "stock") {
      val = $("#fc_comb_global_stock").val();
      fcVariants.forEach(function (v) {
        v.stock = val;
      });
    } else if (target === "status") {
      val = $("#fc_comb_global_status").val();
      fcVariants.forEach(function (v) {
        v.status = val;
      });
    }
    fcSyncVariantsJSON();
    fcRenderVariantsTable();
  });

  // --- Procentowa cena promocyjna: auto-przeliczanie ---
  // Helper: przelicz procent na kwotę
  function fcCalcSalePercent($input) {
    var val = $input.val().trim();
    var pctMatch = val.match(/^-?(\d+(?:[.,]\d+)?)\s*%$/);
    if (!pctMatch) {
      // Ukryj preview dla prostego produktu
      if ($input.attr("id") === "fc_sale_price") {
        $("#fc_sale_preview").hide();
      }
      return;
    }
    var pct = parseFloat(pctMatch[1].replace(",", "."));
    if (pct <= 0 || pct >= 100) return;

    var regPrice = 0;
    // Prosty produkt
    if ($input.attr("id") === "fc_sale_price") {
      regPrice = parseFloat($("#fc_price").val()) || 0;
    } else {
      // Wariant — cena regularna w tym samym wierszu
      var $row = $input.closest("tr");
      regPrice = parseFloat($row.find(".fc-v-price").val()) || 0;
    }

    if (regPrice <= 0) return;
    var calculated = (regPrice * (1 - pct / 100)).toFixed(2);
    $input.val(calculated);

    // Pokaż preview z informacją o rabacie
    if ($input.attr("id") === "fc_sale_price") {
      $("#fc_sale_preview")
        .html(
          "<strong>" +
            fc_admin_vars.i18n.discount_label +
            pct +
            "%</strong> &rarr; " +
            calculated +
            " " +
            (typeof fc_ajax !== "undefined" && fc_ajax.currency
              ? fc_ajax.currency
              : fc_admin_vars.currency),
        )
        .show();
    }
  }

  // Prosty produkt — blur na polu ceny promocyjnej
  $(document).on("blur", "#fc_sale_price", function () {
    fcCalcSalePercent($(this));
  });

  // Warianty — blur na polach ceny promocyjnej w kombinacjach
  $(document).on("blur", ".fc-v-sale", function () {
    fcCalcSalePercent($(this));
  });

  // Wyczyść preview gdy pole puste
  $(document).on("input", "#fc_sale_price", function () {
    if ($(this).val().trim() === "") {
      $("#fc_sale_preview").hide();
    }
  });

  // Combination images — pick from product gallery
  $(document).on("click", ".fc-comb-images-add", function () {
    var $wrap = $(this).closest(".fc-comb-images-wrap");
    var ci = parseInt($wrap.data("index"));
    // Get gallery image IDs
    var galleryVal = $("#fc_gallery_input").val() || "";
    var featId = $("#product_thumbnail").val() || "0";
    var galleryIds = galleryVal ? galleryVal.split(",").map(Number) : [];
    if (parseInt(featId) > 0) {
      galleryIds.unshift(parseInt(featId));
    }
    galleryIds = galleryIds.filter(function (v, i, a) {
      return a.indexOf(v) === i;
    });

    if (galleryIds.length === 0) {
      fcAdminToast(fc_admin_vars.i18n.add_gallery_images_first, "error");
      return;
    }

    var currentIds = (fcVariants[ci] && fcVariants[ci].images) || [];

    // Build picker modal
    var $overlay = $('<div class="fc-gallery-picker-overlay"></div>');
    var $modal = $(
      '<div class="fc-gallery-picker-modal" role="dialog" aria-modal="true" aria-label="' +
        fc_admin_vars.i18n.select_images_from_gallery +
        '">' +
        '<div class="fc-gallery-picker-header"><strong>' +
        fc_admin_vars.i18n.select_images_from_gallery +
        '</strong><button type="button" class="fc-gallery-picker-close" aria-label="' +
        (fc_admin_vars.i18n.close || "Close") +
        '">&times;</button></div>' +
        '<div class="fc-gallery-picker-grid"></div>' +
        '<div class="fc-gallery-picker-footer"><button type="button" class="button button-primary fc-gallery-picker-confirm">' +
        fc_admin_vars.i18n.confirm_selection +
        "</button></div>" +
        "</div>",
    );

    var $grid = $modal.find(".fc-gallery-picker-grid");

    galleryIds.forEach(function (imgId) {
      var thumbUrl = fcVariantImageUrl(imgId);
      if (!thumbUrl) {
        var $thumb = $(
          '#fc_gallery_grid .fc-gallery-thumb[data-id="' + imgId + '"] img',
        );
        if ($thumb.length) thumbUrl = $thumb.attr("src");
      }
      if (!thumbUrl) return;
      var isSelected = currentIds.indexOf(imgId) !== -1;
      $grid.append(
        '<div class="fc-gallery-picker-item' +
          (isSelected ? " selected" : "") +
          '" data-id="' +
          imgId +
          '">' +
          '<img src="' +
          thumbUrl +
          '" alt="">' +
          '<span class="fc-gallery-picker-check">&#10003;</span>' +
          "</div>",
      );
    });

    $("body").append($overlay).append($modal);

    $modal.on("click", ".fc-gallery-picker-item", function () {
      $(this).toggleClass("selected");
    });

    $modal.on("click", ".fc-gallery-picker-confirm", function () {
      var selectedIds = [];
      $modal.find(".fc-gallery-picker-item.selected").each(function () {
        selectedIds.push(parseInt($(this).data("id")));
      });
      if (fcVariants[ci]) {
        fcVariants[ci].images = selectedIds;
        var curMain = parseInt(fcVariants[ci].main_image) || 0;
        if (selectedIds.indexOf(curMain) === -1) {
          fcVariants[ci].main_image = selectedIds.length ? selectedIds[0] : 0;
        }
        // Save thumb URLs for rendering
        selectedIds.forEach(function (id) {
          var src = $modal
            .find('.fc-gallery-picker-item[data-id="' + id + '"] img')
            .attr("src");
          if (src) {
            window.fcVariantThumbs = window.fcVariantThumbs || {};
            window.fcVariantThumbs[id] = src;
          }
        });
        fcSyncVariantsJSON();
        fcRenderVariantsTable();
      }
      $overlay.remove();
      $modal.remove();
    });

    var _closeGalleryPicker = function () {
      $(document).off("keydown.fcGalleryPicker");
      $overlay.remove();
      $modal.remove();
    };

    $modal.on("click", ".fc-gallery-picker-close", _closeGalleryPicker);
    $overlay.on("click", _closeGalleryPicker);
    $(document).on("keydown.fcGalleryPicker", function (e) {
      if (e.key === "Escape") _closeGalleryPicker();
    });

    // Focus first interactive element
    $modal.find(".fc-gallery-picker-confirm").focus();
  });

  // Remove single image from combination
  $(document).on("click", ".fc-comb-img-remove", function (e) {
    e.stopPropagation();
    var $wrap = $(this).closest(".fc-comb-images-wrap");
    var ci = parseInt($wrap.data("index"));
    var imgId = parseInt($(this).closest(".fc-comb-img-item").data("id"));
    if (fcVariants[ci]) {
      fcVariants[ci].images = (fcVariants[ci].images || []).filter(
        function (id) {
          return parseInt(id) !== imgId;
        },
      );
      if (parseInt(fcVariants[ci].main_image) === imgId) {
        fcVariants[ci].main_image = fcVariants[ci].images.length
          ? fcVariants[ci].images[0]
          : 0;
      }
      fcSyncVariantsJSON();
      fcRenderVariantsTable();
    }
  });

  // Click image item to set as main
  $(document).on("click", ".fc-comb-img-item", function () {
    var $wrap = $(this).closest(".fc-comb-images-wrap");
    var ci = parseInt($wrap.data("index"));
    var imgId = parseInt($(this).data("id"));
    if (fcVariants[ci]) {
      fcVariants[ci].main_image = imgId;
      fcSyncVariantsJSON();
      $wrap.find(".fc-comb-img-item").removeClass("fc-comb-img-main");
      $(this).addClass("fc-comb-img-main");
    }
  });

  // Remove combination row
  $(document).on("click", ".fc-comb-remove", function () {
    var ci = parseInt($(this).closest(".fc-combination-row").data("index"));
    if (!isNaN(ci) && fcVariants[ci] !== undefined) {
      fcVariants.splice(ci, 1);
      fcSyncVariantsJSON();
      fcRenderVariantsTable();
    }
  });

  // --- Helper: Cartesian product ---
  function fcCartesian(arrays) {
    if (!arrays.length) return [[]];
    return arrays.reduce(
      function (acc, cur) {
        var result = [];
        acc.forEach(function (a) {
          cur.forEach(function (b) {
            result.push(a.concat([b]));
          });
        });
        return result;
      },
      [[]],
    );
  }

  // --- Helper: Escape HTML ---
  function fcEscHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // --- Helper: Escape attribute ---
  function fcEscAttr(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  // ===================================================================
  //  Digital Product — File picker
  // ===================================================================

  // ===================================================================
  //  Autocomplete / suggestions for attribute names in product editor
  // ===================================================================

  var fcGlobalAttrs = [];
  var fcGlobalAttrsLoaded = false;

  function fcLoadGlobalAttrs(callback) {
    if (fcGlobalAttrsLoaded) {
      if (callback) callback();
      return;
    }
    $.post(
      ajaxurl,
      {
        action: "fc_get_global_attributes",
        _ajax_nonce:
          typeof fc_admin_vars !== "undefined" ? fc_admin_vars.nonce : "",
      },
      function (res) {
        if (res.success) {
          fcGlobalAttrs = res.data;
        }
        fcGlobalAttrsLoaded = true;
        if (callback) callback();
      },
    );
  }

  // ===================================================================
  //  Global Attribute Form (add/edit page)
  // ===================================================================

  if ($("#fc_ga_values_json").length) {
    var gaValues = [];
    try {
      gaValues = JSON.parse($("#fc_ga_values_json").val()) || [];
    } catch (e) {
      gaValues = [];
    }

    function gaGetType() {
      return $("#fc_ga_type").val() || "text";
    }

    function gaSyncJSON() {
      $("#fc_ga_values_json").val(JSON.stringify(gaValues));
    }

    function gaRender() {
      var type = gaGetType();
      var $wrap = $("#fc_ga_values_wrap");
      $wrap.empty();

      var html =
        '<div class="fc-ga-names-input-wrap">' +
        '<input type="text" id="fc_ga_names_raw" class="regular-text" placeholder="' +
        (type === "color"
          ? fc_admin_vars.i18n.placeholder_color
          : type === "image"
            ? fc_admin_vars.i18n.placeholder_image
            : fc_admin_vars.i18n.placeholder_text) +
        '" value="' +
        fcEscAttr(
          gaValues
            .map(function (v) {
              return v.label || "";
            })
            .join(", "),
        ) +
        '">';

      if (type !== "text") {
        html +=
          '<button type="button" class="button" id="fc_ga_generate">' +
          fc_admin_vars.i18n.generate +
          "</button>";
      }
      html += "</div>";

      if (type !== "text") {
        html += '<div class="fc-ga-generated-list" id="fc_ga_generated"></div>';
      }

      $wrap.html(html);

      // Render existing values for color/image
      if (type !== "text") {
        gaRenderGenerated();
      }
    }

    // Locked values (labels used in product variants — cannot remove)
    var gaLockedValues = [];
    try {
      gaLockedValues = JSON.parse($("#fc_ga_locked_values").val() || "[]");
    } catch (e) {
      gaLockedValues = [];
    }

    function gaIsLocked(label) {
      return gaLockedValues.indexOf((label || "").toLowerCase()) !== -1;
    }

    function gaRenderGenerated() {
      var type = gaGetType();
      var $list = $("#fc_ga_generated");
      if (!$list.length) return;
      $list.empty();

      gaValues.forEach(function (val, vi) {
        var rowHtml = "";
        var locked = gaIsLocked(val.label);
        var removeBtn = locked
          ? '<span class="fc-ga-locked-icon dashicons dashicons-lock" title="' +
            fc_admin_vars.i18n.value_used_in_variants +
            '"></span>'
          : '<button type="button" class="fc-ga-remove" data-val-index="' +
            vi +
            '">&times;</button>';
        if (type === "color") {
          var cval = val.value || "#000000";
          rowHtml =
            '<div class="fc-attr-gen-row fc-attr-gen-color-row' +
            (locked ? " fc-ga-row-locked" : "") +
            '" data-val-index="' +
            vi +
            '">' +
            '<span class="fc-attr-gen-label">' +
            fcEscHtml(val.label || "") +
            "</span>" +
            '<input type="color" class="fc-ga-color" data-val-index="' +
            vi +
            '" value="' +
            fcEscAttr(cval) +
            '">' +
            '<span class="fc-attr-gen-color-hex">' +
            fcEscHtml(cval) +
            "</span>" +
            removeBtn +
            "</div>";
        } else if (type === "image") {
          var imgUrl = val.url || "";
          rowHtml =
            '<div class="fc-attr-gen-row fc-attr-gen-image-row' +
            (locked ? " fc-ga-row-locked" : "") +
            '" data-val-index="' +
            vi +
            '">' +
            '<span class="fc-attr-gen-label">' +
            fcEscHtml(val.label || "") +
            "</span>" +
            '<div class="fc-attr-gen-image-pick">' +
            (imgUrl
              ? '<img src="' +
                imgUrl +
                '" alt="" class="fc-attr-gen-image-preview">'
              : "") +
            '<button type="button" class="button button-small fc-ga-image-choose" data-val-index="' +
            vi +
            '">' +
            (imgUrl ? fc_admin_vars.i18n.change : fc_admin_vars.i18n.select) +
            "</button>" +
            "</div>" +
            removeBtn +
            "</div>";
        }
        $list.append(rowHtml);
      });
    }

    // Type change (blocked when in use — select is disabled)
    $(document).on("change", "#fc_ga_type", function () {
      gaValues = [];
      gaSyncJSON();
      gaRender();
    });

    // Generate
    $(document).on("click", "#fc_ga_generate", function () {
      var raw = $("#fc_ga_names_raw").val();
      var names = raw
        .split(",")
        .map(function (n) {
          return n.trim();
        })
        .filter(function (n) {
          return n.length > 0;
        });

      var type = gaGetType();

      // Ensure all locked labels are present in names list
      gaLockedValues.forEach(function (lv) {
        var found = false;
        for (var i = 0; i < names.length; i++) {
          if (names[i].toLowerCase() === lv) {
            found = true;
            break;
          }
        }
        if (!found) {
          // Find original label casing from gaValues
          for (var j = 0; j < gaValues.length; j++) {
            if ((gaValues[j].label || "").toLowerCase() === lv) {
              names.push(gaValues[j].label);
              break;
            }
          }
        }
      });

      if (!names.length) return;

      var newValues = [];
      names.forEach(function (name) {
        // preserve existing values that match by label
        var existing = null;
        for (var i = 0; i < gaValues.length; i++) {
          if ((gaValues[i].label || "").toLowerCase() === name.toLowerCase()) {
            existing = gaValues[i];
            break;
          }
        }
        if (existing) {
          newValues.push(existing);
        } else {
          if (type === "color") {
            newValues.push({ label: name, value: "#000000" });
          } else if (type === "image") {
            newValues.push({ label: name, id: 0, url: "" });
          }
        }
      });
      gaValues = newValues;
      gaSyncJSON();
      gaRenderGenerated();
    });

    // Validate variant prices before form submit
    $("form.fc-admin-form").on("submit", function (e) {
      var $type = $("#fc_product_type");
      if ($type.length && $type.val() === "variable") {
        var hasEmpty = false;
        for (var vi = 0; vi < fcVariants.length; vi++) {
          if (fcVariants[vi].status === "inactive") continue;
          if (!fcVariants[vi].price || $.trim(fcVariants[vi].price) === "") {
            hasEmpty = true;
            break;
          }
        }
        if (hasEmpty) {
          e.preventDefault();
          fcAdminToast(fc_admin_vars.i18n.error_fill_variant_prices, "error");
          $(".fc-v-price")
            .filter(function () {
              return $.trim($(this).val()) === "";
            })
            .first()
            .css("border-color", "#d63638")
            .css("box-shadow", "0 0 0 1px #d63638")
            .focus();
          return false;
        }
        // Ensure JSON is synced
        fcSyncVariantsJSON();
      }
    });

    // Clear price error highlight on input
    $(document).on("input", ".fc-comb-price", function () {
      if ($.trim($(this).val()) !== "") {
        $(this).css("border-color", "").css("box-shadow", "");
      }
    });

    // For text type: sync from raw input on form submit
    $("form.fc-attr-global-form").on("submit", function () {
      var type = gaGetType();
      if (type === "text") {
        var raw = $("#fc_ga_names_raw").val();
        var names = raw
          .split(",")
          .map(function (n) {
            return n.trim();
          })
          .filter(function (n) {
            return n.length > 0;
          });
        // Ensure locked values are preserved
        gaLockedValues.forEach(function (lv) {
          var found = false;
          for (var i = 0; i < names.length; i++) {
            if (names[i].toLowerCase() === lv) {
              found = true;
              break;
            }
          }
          if (!found) {
            // Find original label casing
            for (var j = 0; j < gaValues.length; j++) {
              if ((gaValues[j].label || "").toLowerCase() === lv) {
                names.push(gaValues[j].label);
                break;
              }
            }
          }
        });
        gaValues = names.map(function (n) {
          return { label: n };
        });
        gaSyncJSON();
      }
    });

    // Color picker change
    $(document).on("input change", ".fc-ga-color", function () {
      var vi = parseInt($(this).data("val-index"), 10);
      if (gaValues[vi]) {
        gaValues[vi].value = $(this).val();
        $(this).siblings(".fc-attr-gen-color-hex").text($(this).val());
        gaSyncJSON();
      }
    });

    // Image choose
    $(document).on("click", ".fc-ga-image-choose", function () {
      var vi = parseInt($(this).data("val-index"), 10);
      var frame = wp.media({
        title: fc_admin_vars.i18n.media_select_image,
        multiple: false,
        library: { type: "image" },
      });
      frame.on("select", function () {
        var att = frame.state().get("selection").first().toJSON();
        var thumbUrl =
          att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
        if (gaValues[vi]) {
          gaValues[vi].id = att.id;
          gaValues[vi].url = thumbUrl;
          gaSyncJSON();
          gaRenderGenerated();
        }
      });
      frame.open();
    });

    // Remove value (skip if locked)
    $(document).on("click", ".fc-ga-remove", function () {
      var vi = parseInt($(this).data("val-index"), 10);
      if (gaValues[vi] && gaIsLocked(gaValues[vi].label)) {
        return; // safety check
      }
      gaValues.splice(vi, 1);
      gaSyncJSON();
      gaRenderGenerated();
      // Update raw input
      $("#fc_ga_names_raw").val(
        gaValues
          .map(function (v) {
            return v.label || "";
          })
          .join(", "),
      );
    });

    // Init render
    gaRender();
  }

  // ===================================================================
  //  Blocked attribute action — show products info modal
  // ===================================================================
  $(document).on("click", ".fc-attr-action-blocked", function () {
    var products = $(this).data("products");
    if (!products || !products.length) return;

    var html =
      '<div class="fc-attr-blocked-overlay"></div>' +
      '<div class="fc-attr-blocked-modal" role="dialog" aria-modal="true" aria-label="' +
      fc_admin_vars.i18n.attr_used_in_products +
      '">' +
      '<div class="fc-gallery-picker-header"><strong>' +
      fc_admin_vars.i18n.attr_used_in_products +
      '</strong><button type="button" class="fc-attr-blocked-close" aria-label="' +
      (fc_admin_vars.i18n.close || "Close") +
      '">&times;</button></div>' +
      '<div style="padding: 16px;">' +
      "<p>" +
      fc_admin_vars.i18n.attr_blocked_explanation +
      "</p><ul>";

    products.forEach(function (p) {
      html +=
        '<li><a href="' +
        (typeof ajaxurl !== "undefined"
          ? ajaxurl.replace("admin-ajax.php", "admin.php")
          : "/wp-admin/admin.php") +
        "?page=fc-product-add&product_id=" +
        p.id +
        '" target="_blank">' +
        fcEscHtml(p.title) +
        "</a></li>";
    });

    html +=
      "</ul><p>" +
      fc_admin_vars.i18n.attr_blocked_instructions +
      "</p></div></div>";

    $("body").append(html);
    $(".fc-attr-blocked-close").focus();
  });

  $(document).on(
    "click",
    ".fc-attr-blocked-close, .fc-attr-blocked-overlay",
    function () {
      $(document).off("keydown.fcAttrBlocked");
      $(".fc-attr-blocked-overlay, .fc-attr-blocked-modal").remove();
    },
  );
  $(document).on("keydown.fcAttrBlocked", function (e) {
    if (e.key === "Escape" && $(".fc-attr-blocked-modal").length) {
      $(document).off("keydown.fcAttrBlocked");
      $(".fc-attr-blocked-overlay, .fc-attr-blocked-modal").remove();
    }
  });

  // ===== Digital: File picker =====
  $(document).on("click", "#fc_choose_file", function (e) {
    e.preventDefault();

    var frame = wp.media({
      title: fc_admin_vars.i18n.media_select_download,
      multiple: false,
    });

    frame.on("select", function () {
      var att = frame.state().get("selection").first().toJSON();
      $("#fc_digital_file").val(att.url);
    });

    frame.open();
  });

  // ===== Units: inline edit & bulk check-all =====
  (function () {
    if (!$(".fc-units-page").length) return;

    // Check all
    $("#fc_unit_check_all").on("change", function () {
      $(".fc-unit-cb").prop("checked", this.checked);
      updateCounter();
    });

    // Individual checkbox
    $(document).on("change", ".fc-unit-cb", function () {
      var total = $(".fc-unit-cb").length;
      var checked = $(".fc-unit-cb:checked").length;
      $("#fc_unit_check_all").prop("checked", checked === total);
      updateCounter();
    });

    // Floating counter following cursor
    var $counter = $('<div class="fc-unit-selection-counter"></div>')
      .appendTo("body")
      .hide();

    // Stały przycisk resetowania zaznaczenia (jak w WP list table)
    var $resetBtn = $(
      '<button type="button" class="fc-unit-reset-selection" style="display:none;">' +
        '<span class="fc-reset-text"></span> <span class="fc-reset-x">&times;</span>' +
        "</button>",
    );
    $(".fc-units-list .fc-units-bulk-bar").after($resetBtn);

    function updateCounter() {
      var count = $(".fc-unit-cb:checked").length;
      if (count > 0) {
        $counter
          .text(count + " " + fc_admin_vars.i18n.units_selected_count)
          .show();
        $resetBtn
          .find(".fc-reset-text")
          .text(fc_admin_vars.i18n.reset_selection);
        $resetBtn.show();
      } else {
        $counter.hide();
        $resetBtn.hide();
      }
    }

    $resetBtn.on("click", function () {
      $(".fc-unit-cb, #fc_unit_check_all").prop("checked", false);
      updateCounter();
    });

    $(document).on("mousemove", ".fc-units-list", function (e) {
      if ($counter.is(":visible")) {
        $counter.css({ left: e.clientX + 14, top: e.clientY + 14 });
      }
    });

    $(document).on("mouseleave", ".fc-units-list", function () {
      if ($counter.is(":visible")) {
        $counter.hide();
      }
    });

    $(document).on("mouseenter", ".fc-units-list", function () {
      var count = $(".fc-unit-cb:checked").length;
      if (count > 0) {
        $counter.show();
      }
    });

    // Inline edit: show
    $(document).on("click", ".fc-unit-edit-link", function (e) {
      e.preventDefault();
      var $row = $(this).closest(".fc-unit-row");
      $row.find(".fc-unit-name-display, .fc-unit-default-badge").hide();
      $row.find(".fc-unit-inline-edit").show();
      $row.find(".fc-unit-edit-input").focus().select();
    });

    // Inline edit: cancel
    $(document).on("click", ".fc-unit-edit-cancel", function () {
      var $row = $(this).closest(".fc-unit-row");
      var original = $row.data("unit");
      $row.find(".fc-unit-edit-input").val(original);
      $row.find(".fc-unit-inline-edit").hide();
      $row.find(".fc-unit-name-display, .fc-unit-default-badge").show();
    });

    // Inline edit: save
    $(document).on("click", ".fc-unit-edit-save", function () {
      var $row = $(this).closest(".fc-unit-row");
      var oldName = $row.data("unit");
      var newName = $row.find(".fc-unit-edit-input").val().trim();
      if (!newName || newName === oldName) {
        $row.find(".fc-unit-edit-cancel").click();
        return;
      }
      $("#fc_edit_old_name").val(oldName);
      $("#fc_edit_new_name").val(newName);
      $("#fc_edit_unit_form").submit();
    });

    // Inline edit: Enter/Escape
    $(document).on("keydown", ".fc-unit-edit-input", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $(this).closest(".fc-unit-row").find(".fc-unit-edit-save").click();
      } else if (e.key === "Escape") {
        $(this).closest(".fc-unit-row").find(".fc-unit-edit-cancel").click();
      }
    });
  })();

  // ===== Taxonomy name field: required asterisk =====
  (function () {
    var $body = $("body");
    if (
      $body.hasClass("taxonomy-fc_product_cat") ||
      $body.hasClass("taxonomy-fc_product_brand")
    ) {
      // Add form (left side)
      var $addLabel = $("#tag-name").closest(".form-field").find("label");
      if ($addLabel.length && $addLabel.find(".required").length === 0) {
        $addLabel.append(' <span class="required">*</span>');
      }
      // Edit form
      var $editLabel = $("input#name").closest(".form-field").find("label");
      if ($editLabel.length && $editLabel.find(".required").length === 0) {
        $editLabel.append(' <span class="required">*</span>');
      }
    }
  })();

  /* ================================================================
     Brand logo upload (taxonomy page + inline form)
     ================================================================ */
  (function () {
    function openMediaPicker($input, $preview, $removeBtn) {
      var frame = wp.media({
        title: fc_admin_vars.i18n.media_select_brand_logo,
        button: { text: fc_admin_vars.i18n.media_use_as_logo },
        multiple: false,
        library: { type: "image", fc_brand_logos: true },
      });
      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        var url =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;
        $input.val(attachment.id);
        $preview
          .html('<img src="' + url + '" style="max-width:120px;height:auto;">')
          .show();
        $removeBtn.show();
      });
      frame.open();
    }

    // Taxonomy page: add & edit form
    $(document).on("click", "#fc_brand_logo_btn", function (e) {
      e.preventDefault();
      openMediaPicker(
        $("#fc_brand_logo"),
        $("#fc_brand_logo_preview"),
        $("#fc_brand_logo_remove"),
      );
    });
    $(document).on("click", "#fc_brand_logo_remove", function (e) {
      e.preventDefault();
      $("#fc_brand_logo").val("");
      $("#fc_brand_logo_preview").empty().hide();
      $(this).hide();
    });

    // Reset logo fields after add term form submit (WP reloads via AJAX)
    $(document).ajaxComplete(function (event, xhr, settings) {
      if (
        settings.data &&
        typeof settings.data === "string" &&
        settings.data.indexOf("action=add-tag") !== -1
      ) {
        $("#fc_brand_logo").val("");
        $("#fc_brand_logo_preview").empty().hide();
        $("#fc_brand_logo_remove").hide();
      }
    });

    // Inline brand add form (product edit page)
    $(document).on("click", "#fc_new_brand_logo_btn", function (e) {
      e.preventDefault();
      openMediaPicker(
        $("#fc_new_brand_logo"),
        $("#fc_new_brand_logo_preview"),
        $("#fc_new_brand_logo_remove"),
      );
    });
    $(document).on("click", "#fc_new_brand_logo_remove", function (e) {
      e.preventDefault();
      $("#fc_new_brand_logo").val("");
      $("#fc_new_brand_logo_preview").empty().hide();
      $(this).hide();
    });
  })();

  // ===== Select filter — search/filter for <select multiple> =====
  $(document).on("input", ".fc-select-filter", function () {
    var query = $(this).val().toLowerCase();
    var targetId = $(this).data("target");
    var $select = targetId ? $("#" + targetId) : $(this).next("select");
    $select.find("option").each(function () {
      var text = $(this).text().toLowerCase();
      if (
        query === "" ||
        text.indexOf(query) !== -1 ||
        $(this).is(":selected")
      ) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  });

  /* ── CSV Export for Orders ──────────────────────────── */
  $(document).on("click", ".fc-export-csv-btn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    $btn.prop("disabled", true).text("⏳");

    var params = new URLSearchParams(window.location.search);
    var data = {
      action: "fc_export_orders_csv",
      nonce: fc_admin_vars.nonce,
      fc_status: params.get("fc_status") || "",
      fc_search: $('input[name="fc_search"]').val() || "",
      fc_date_from: $('input[name="fc_date_from"]').val() || "",
      fc_date_to: $('input[name="fc_date_to"]').val() || "",
    };

    $.post(ajaxurl, data, function (res) {
      if (res.success && res.data.csv) {
        var raw = atob(res.data.csv);
        var bytes = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
        var blob = new Blob([bytes], { type: "text/csv;charset=utf-8;" });
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = res.data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        fcAdminToast(fc_admin_vars.i18n.saved || "OK", "success");
      } else {
        fcAdminToast(fc_admin_vars.i18n.save_error || "Error", "error");
      }
    })
      .fail(function () {
        fcAdminToast(fc_admin_vars.i18n.save_error || "Error", "error");
      })
      .always(function () {
        $btn
          .prop("disabled", false)
          .text(fc_admin_vars.i18n.export_csv || "Export CSV");
      });
  });
})(jQuery);
