(function (Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.aiProviderOpenrouterForceStream = {
    attach: function (context, settings) {
      const cfg = drupalSettings.ai_deepchat;
      if (!cfg) return;
      // Do not force streaming for agent-based assistants.
      if (cfg.is_agent === true) return;
      try {
        // connect in deepchat_settings is JSON string; normalize to object.
        let connect = cfg.connect;
        if (typeof connect === 'string') {
          try { connect = JSON.parse(connect); } catch (e) { connect = {}; }
        }
        if (typeof connect !== 'object' || connect === null) connect = {};
        connect.stream = true;
        connect.additionalBodyProps = connect.additionalBodyProps || {};
        // Use boolean true to align with OpenRouter/DeepChat conventions.
        connect.additionalBodyProps.stream = true;
        // Write back both to deepchat_settings and drupalSettings for any code reading either form.
        cfg.connect = JSON.stringify(connect);
        drupalSettings.ai_deepchat = cfg;
      } catch (e) {
        // Silently ignore.
      }
    }
  };
})(Drupal, drupalSettings);
