<?php

namespace Honeygavi\Provisioner;

use AgaviConfig;
use DirectoryIterator;
use Honeybee\Common\Error\RuntimeError;
use Honeygavi\Renderer\Twig\HoneybeeToolkitExtension;
use Honeygavi\Renderer\Twig\MarkdownExtension;
use Honeybee\Infrastructure\Config\Settings;
use Honeybee\Infrastructure\Config\SettingsInterface;
use Honeygavi\Template\TemplateRendererInterface;
use Honeygavi\Template\Twig\Extension\ToolkitExtension;
use Honeygavi\Template\Twig\Extension\TranslatorExtension;
use Honeygavi\Template\Twig\Extension\UrlGeneratorExtension;
use Honeygavi\Template\Twig\Extension\EnvironmentExtension;
use Honeygavi\Template\Twig\Loader\FilesystemLoader;
use Honeybee\ServiceDefinitionInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;

class TemplateRendererProvisioner extends AbstractProvisioner
{
    public function build(ServiceDefinitionInterface $service_definition, SettingsInterface $provisioner_settings)
    {
        $service = $service_definition->getClass();

        $state = [
            ':twig' => $this->createTwigRenderer($this->getTwigTemplateRendererSettings($provisioner_settings))
        ];

        $this->di_container
            ->define($service, $state)
            ->share($service)
            ->alias(TemplateRendererInterface::CLASS, $service);
    }

    protected function createTwigRenderer(SettingsInterface $settings)
    {
        $loader = $this->createTwigLoader($settings);

        $twig = new Environment($loader, (array)$settings->get('twig_options', []));

        $twig_extensions = (array)$settings->get('twig_extensions', []);
        foreach ($twig_extensions as $extension_class) {
            if (is_object($extension_class)) {
                $twig->addExtension($extension_class);
            } else {
                $twig->addExtension(new $extension_class());
            }
        }

        return $twig;
    }

    protected function getTwigTemplateRendererSettings(SettingsInterface $provisioner_settings)
    {
        $settings = [];

        $cache = false;
        if (!AgaviConfig::get('core.debug', false)) {
            $cache = AgaviConfig::get('core.cache_dir') . DIRECTORY_SEPARATOR . 'templates_twig';
        }

        $settings['twig_options'] = [
            'autoescape' => 'html',
            'strict_variables' => false,
            'debug' => AgaviConfig::get('core.debug', false),
            'cache' => $cache
        ];

        $settings['twig_extensions'] = [
            ToolkitExtension::class,
            HoneybeeToolkitExtension::class,
            TranslatorExtension::class,
            UrlGeneratorExtension::class,
            EnvironmentExtension::class,
            IntlExtension::class,
            MarkdownExtension::class
        ];

        if ($settings['twig_options']['debug'] === true) {
            $settings['twig_extensions'][] = DebugExtension::class;
        }

        $settings['template_paths'] = [
            AgaviConfig::get('core.template_dir'),
            AgaviConfig::get('core.honeybee_template_dir')
        ];

        $settings['allowed_template_extensions'] = [
            '.twig',
            '.html'
        ];

        $settings = array_replace_recursive($settings, $provisioner_settings->toArray());

        // instantiate the wanted twig extensions here (as they might need dependencies)
        $twig_extensions = $settings['twig_extensions'];
        $settings['twig_extensions'] = [];
        foreach ($twig_extensions as $extension) {
            $settings['twig_extensions'][] = $this->di_container->make($extension);
        }

        return new Settings($settings);
    }

    protected function createTwigLoader(SettingsInterface $settings)
    {
        if (!$settings->has('template_paths')) {
            throw new RuntimeError('Missing "template_paths" settings with template lookup locations.');
        }

        $template_paths = (array)$settings->get('template_paths', []);

        $loader = new FilesystemLoader($template_paths);
        if ($settings->has('allowed_template_extensions')) {
            $loader->setAllowedExtensions((array)$settings->get('allowed_template_extensions'));
        }

        if ($settings->has('cache_scope')) {
            $loader->setScope($settings->get('cache_scope', FilesystemLoader::SCOPE_DEFAULT));
        }

        // adds an @namespaces to templates to allow twig templates to use embed/include statements that reuse
        // existing templates to override blocks instead of copying whole templates (from different locations)
        // usage example: {% include "@Honeybee/foo.twig" ignore if missing %}
        $loader->addPath(AgaviConfig::get('core.template_dir'), 'App');
        $loader->addPath(AgaviConfig::get('core.honeybee_template_dir'), 'Honeybee');

        foreach ($this->getModuleTemplatesPaths() as $module_name => $templates_path) {
            $loader->addPath($templates_path, $module_name);
        }

        return $loader;
    }

    protected function getModuleTemplatesPaths()
    {
        $paths = [];

        $directory_iterator = new DirectoryIterator(AgaviConfig::get('core.module_dir'));
        foreach ($directory_iterator as $module_directory) {
            if ($module_directory->isDot() || !$module_directory->isDir()) {
                continue;
            }

            $templates_path = $module_directory->getPathname() . DIRECTORY_SEPARATOR . 'templates';
            if (is_readable($templates_path)) {
                $paths[$module_directory->getFilename()] = $templates_path;
            }
        }

        return $paths;
    }
}
