<?php

namespace Honeybee\Ui\Renderer\Html\Trellis\Runtime\Attribute\UrlList;

use Honeybee\Common\Util\StringToolkit;
use Honeybee\Ui\Renderer\Html\Trellis\Runtime\Attribute\HtmlAttributeRenderer;
use Trellis\Runtime\Attribute\UrlList\UrlListAttribute;

class HtmlUrlListAttributeRenderer extends HtmlAttributeRenderer
{
    protected function getDefaultTemplateIdentifier()
    {
        $view_scope = $this->getOption('view_scope', 'missing_view_scope.collection');
        if (StringToolkit::endsWith($view_scope, 'collection')) {
            return $this->output_format->getName() . '/attribute/url-list/as_itemlist_item_cell.twig';
        }

        return $this->output_format->getName() . '/attribute/url-list/as_input.twig';
    }

    protected function getTemplateParameters()
    {
        $params = parent::getTemplateParameters();

        $params['grouped_field_name'] = $params['grouped_field_name'] . '[]';

        $params['maxlength'] = $this->getOption(
            'maxlength',
            $this->attribute->getOption(UrlListAttribute::OPTION_MAX_LENGTH)
        );

        return $params;
    }

    protected function determineAttributeValue($attribute_name, $default_value = '')
    {
        $value = [];

        if ($this->hasOption('value')) {
            $value = $this->getOption('value', $default_value);
            $value = is_array($value) ? $value : [ $value ];
            return $value;
        }

        $expression = $this->getOption('expression');
        if (!empty($expression)) {
            $value = $this->evaluateExpression($expression);
        } else {
            $value = $this->getPayload('resource')->getValue($attribute_name);
        }

        $value = is_array($value) ? $value : [ $value ];

        return $value;
    }

    protected function getWidgetOptions()
    {
        $widget_options = parent::getWidgetOptions();

        $widget_options['min_count'] = $this->getMinCount($this->isRequired());
        $widget_options['max_count'] = $this->getMaxCount();

        return $widget_options;
    }

    protected function getMinCount($is_required = false)
    {
        $min_count = $this->getOption(
            'min_count',
            $this->attribute->getOption(UrlListAttribute::OPTION_MIN_COUNT)
        );

        if (!is_numeric($min_count) && $is_required) {
            $min_count = 1;
        }

        return $min_count;
    }

    protected function getMaxCount()
    {
        return $this->getOption(
            'max_count',
            $this->attribute->getOption(UrlListAttribute::OPTION_MAX_COUNT)
        );
    }

    protected function isRequired()
    {
        $is_required = parent::isRequired();

        $url_list = $this->determineAttributeValue($this->attribute->getName());

        // check options against actual value
        $items_number = count($url_list);
        $min_count = $this->getMinCount($is_required);

        if (is_numeric($min_count) && $items_number < $min_count) {
            $is_required = true;
        }

        return $is_required;
    }

    protected function getWidgetImplementor()
    {
        return $this->getOption('widget', '');
    }
}
