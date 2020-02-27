<?php

namespace Honeygavi\Template\Twig\Loader;

use MtHaml\Environment;
use Twig\Error\LoaderError;
use Twig\ExistsLoaderInterface;
use Twig\LoaderInterface;
use Twig\Source;
use Twig\SourceContextLoaderInterface;

/**
 * Example integration of MtHaml with Twig, by proxying the Loader
 *
 * This loader will parse Twig templates as HAML if their filename end with
 * `.haml`, or if the code starts with `{% haml %}`.
 *
 * Alternatively, use MtHaml\Support\Twig\Lexer.
 *
 * <code>
 * $origLoader = $twig->getLoader();
 * $twig->setLoader($mthaml, new \MtHaml\Support\Twig\Loader($origLoader));
 * </code>
 */
class MtHamlTwigLoader implements LoaderInterface, ExistsLoaderInterface, SourceContextLoaderInterface
{
    protected $env;
    protected $loader;

    public function __construct(Environment $env, LoaderInterface $loader)
    {
        $this->env = $env;
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceContext($name)
    {
        $source = $this->loader->getSourceContext($name);

        $code = $source->getCode();
        $code = $this->renderHaml($name, $code);

        $source = new Source($code, $source->getName(), $source->getPath());

        return $source;
    }

    protected function renderHaml($name, $code)
    {
        if ('haml' === pathinfo($name, PATHINFO_EXTENSION)) {
            $code = $this->env->compileString($code, $name);
        } elseif (preg_match('#^\s*{%\s*haml\s*%}#', $code, $match)) {
            $padding = str_repeat(' ', strlen($match[0]));
            $code = $padding . substr($code, strlen($match[0]));
            $code = $this->env->compileString($code, $name);
        }

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        return $this->loader->getCacheKey($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        return $this->loader->isFresh($name, $time);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        if ($this->loader instanceof ExistsLoaderInterface) {
            return $this->loader->exists($name);
        }

        if ($this->loader instanceof SourceContextLoaderInterface) {
            try {
                $this->loader->getSourceContext($name);

                return true;
            } catch (LoaderError $e) {
                return false;
            }
        }
    }
}
