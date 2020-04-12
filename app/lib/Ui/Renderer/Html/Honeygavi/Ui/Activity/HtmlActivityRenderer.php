<?php

namespace Honeygavi\Ui\Renderer\Html\Honeygavi\Ui\Activity;

use Honeygavi\Ui\Renderer\ActivityRenderer;

class HtmlActivityRenderer extends ActivityRenderer
{
    protected function getTemplateParameters()
    {
        $activity = $this->getPayload('subject');

        $default_css = [
            'activity',
            'activity-' . strtolower($activity->getName())
        ];

        $params = [];
        $params['target'] = $this->getOption('target');
        $params['css'] = $this->getOption('css', $default_css);
        $params['form_id'] = $this->getOption('form_id', $activity->getSettings()->get('form_id', 'randomFormId-'.mt_rand()));
        $params['form_parameters'] = $this->getOption('form_parameters', $activity->getUrl()->getParameters());
        $params['form_method'] = $this->getOption('form_method', ($activity->getVerb() === 'read') ? 'GET' : 'POST');
        $params['form_css'] = $this->getOption('form_css');
        $params['rendered_additional_markup'] = (array)$this->getOption('rendered_additional_markup', []);
        $params['activity_map_options'] = $this->getOption('activity_map_options', []);
        $params['disabled'] = $this->getOption('disabled', false);
        if ($params['disabled']) {
            $params['css'][] = 'disabled';
        }

        return array_replace_recursive(parent::getTemplateParameters(), $params);
    }
}
