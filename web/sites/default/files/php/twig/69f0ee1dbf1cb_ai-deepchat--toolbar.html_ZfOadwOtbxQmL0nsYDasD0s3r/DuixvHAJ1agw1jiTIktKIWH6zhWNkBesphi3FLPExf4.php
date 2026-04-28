<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat--toolbar.html.twig */
class __TwigTemplate_96c565c8272eb08e61eb39f2625b9c8b extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("ai_chatbot/toolbar-chatbot"), "html", null, true);
        yield "
";
        // line 2
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "expansion_method", [], "any", false, false, true, 2) == "fullscreen")) {
            // line 3
            yield "  ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("ai_chatbot/toolbar-fullscreen"), "html", null, true);
            yield "
";
        }
        // line 5
        yield "
<div class=\"";
        // line 6
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["current_theme"] ?? null), "html", null, true);
        yield " ai-deepchat chat-container ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "placement", [], "any", false, false, true, 6), "html", null, true);
        yield "\" data-chat-id=\"bot1\">
  <div class=\"sidebar-header\">
    <div class=\"sidebar-header--title\">
     Assistant
    </div>

    <div class=\"chat-dropdown\">
      <button title=\"";
        // line 13
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Toggle chat dropdown"));
        yield "\" class=\"chat-dropdown-button sidebar-header--icon toolbar-button\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Toggle chat dropdown"));
        yield "\">
      </button>
      <div class=\"chat-dropdown-content\">
        <button class=\"clear-history chat-dropdown-link\">";
        // line 16
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Clear History"));
        yield "</button>
      </div>
    </div>

    ";
        // line 20
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "expansion_method", [], "any", false, false, true, 20) != "none")) {
            // line 21
            yield "    <button
      data-expansion-method=\"";
            // line 22
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "expansion_method", [], "any", false, false, true, 22), "html", null, true);
            yield "\"
      title=\"";
            // line 23
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Expand chat"));
            yield "\" class=\"sidebar-header--icon toolbar-button expand\"
      aria-label=\"";
            // line 24
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Expand chat"));
            yield "\"
      aria-expanded=\"false\">
    </button>
    ";
        }
        // line 28
        yield "
    <button title=\"";
        // line 29
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Close chat"));
        yield "\" class=\"sidebar-header--icon toolbar-button close\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Close chat"));
        yield "\" data-action=\"close-chat\">
    </button>
  </div>

    <deep-chat
    ";
        // line 34
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["deepchat_settings"] ?? null));
        foreach ($context['_seq'] as $context["key"] => $context["value"]) {
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $context["key"], "html", null, true);
            yield "='";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($context["value"]);
            yield "'
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['key'], $context['value'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 36
        yield "    >
      <div class=\"chat-header\">
        <span class=\"chat-header--icon\"></span>
        <div class=\"chat-header--title\">
          ";
        // line 40
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(Twig\Extension\CoreExtension::nl2br(t("Hi there! 
 Welcome to 
 Drupal")));
        yield "
          <div class=\"chat-header--title-emoji\">👋</div>
          <br />
          <span class=\"chat-header--subtitle\">";
        // line 43
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("How can I help you today?"));
        yield "</span>
        </div>
      </div>
    </deep-chat>
\t</div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["settings", "current_theme", "deepchat_settings"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat--toolbar.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  146 => 43,  138 => 40,  132 => 36,  119 => 34,  109 => 29,  106 => 28,  99 => 24,  95 => 23,  91 => 22,  88 => 21,  86 => 20,  79 => 16,  71 => 13,  59 => 6,  56 => 5,  50 => 3,  48 => 2,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat--toolbar.html.twig", "/var/www/html/web/modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat--toolbar.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 2, "for" => 34];
        static $filters = ["escape" => 1, "t" => 13, "raw" => 34, "nl2br" => 40];
        static $functions = ["attach_library" => 1];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'for'],
                ['escape', 't', 'raw', 'nl2br'],
                ['attach_library'],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
