/**
 * @file
 * Javascript, modifications of DOM.
 *
 * Manipulates links to include jquery load funciton
 */

(function ($) {
  Drupal.behaviors.jqueryAjaxLoad = {
    attach: function (context, settings) {
      jQuery.ajaxSetup({
        // Disable caching of AJAX responses
        cache: false
      });
      var jal_settings = drupalSettings.jquery_ajax_load;

      var presets = jal_settings.presets;
      $.each(presets, function (index, item) {
        $(item.trigger).once('jal').each(function () {
          var href = $(this).attr('href');
          // Hay que validar si la ruta trae la URL del sitio
          $(this).attr('href', item.target);
          var data_target = $(this).attr('data-target');
          if (typeof data_target === 'undefined') {
            data_target = item.target;
          } else {
            data_target = '#' + data_target;
          }
          $(this).click(function (event) {
            event.preventDefault();
            jquery_ajax_load_load($(this), data_target, href, item);
          });
        });
        $(item.trigger).removeClass(item.trigger);
      });
    }
  };

// Handles link calls
  function jquery_ajax_load_load(el, target, url, item) {
    var jal_settings = drupalSettings.jquery_ajax_load, module_path = jal_settings.module_path,
      toggle = jal_settings.toggle, base_path = jal_settings.base_path, animation = jal_settings.animation,
      selector = target.toString().replace(/^\#|\./i, "");
    var loading_html = Drupal.t('Loading');
    loading_html += '... <img src="/';
    loading_html += module_path;
    loading_html += '/jquery_ajax_load_loading.gif">';

    if (toggle && $(el).hasClass("jquery_ajax_load_open")) {
      $(el).removeClass("jquery_ajax_load_open");
      if (animation) {
        $(target).hide('slow', function () {
          $(target).empty();
        });
      } else {
        $(target).empty();
      }
    } else {

      if (item.behaviour !== 'replace') {
        selector = selector + "-jquery-ajax-load-" + Math.random().toString(36).substring(7);
        loading_html = $('<div>', {id: selector}).html(loading_html);
      }
      switch (item.behaviour) {
        case "prepend":
          $(target).prepend(loading_html);
          break;
        case "append":
        case "dialog":
          $(target).append(loading_html);
          break;
        default:
          $(target).html(loading_html);
      }

      var url = base_path + 'jquery_ajax_load/load?requestUrl=' + url + "&selector=" + selector + "&behaviour=" + item.behaviour;

      let elementSettings = {
        type: 'GET',
        url: url,
        dataType: 'json'
      };

      let jalAjax = new Drupal.ajax(elementSettings);
      var realInsert = Drupal.AjaxCommands.prototype.insert;

      jalAjax.commands.insert = function (ajax, response, status) {
        if ( status == "error" ) {
          var msg = "Sorry but there was an error: ";
          $(target).html( msg + xhr.status + " " + xhr.statusText );
        }
        else {
          if ( animation ) {
            $(target).hide();
            $(target).show('slow')
          }
          $(target).each(function (index, item) {
            Drupal.attachBehaviors(item);
          });
        }
        realInsert(ajax, response, status);
      };
      jalAjax.execute();
      $(el).addClass("jquery_ajax_load_open");
    }
  }
}(jQuery));
