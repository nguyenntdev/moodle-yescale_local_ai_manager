<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aitool_dalle;

use local_ai_manager\base_connector;
use local_ai_manager\local\aitool_option_azure;
use local_ai_manager\local\prompt_response;
use local_ai_manager\local\unit;
use local_ai_manager\local\usage;
use local_ai_manager\request_options;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Connector for Dall-E.
 *
 * @package    aitool_dalle
 * @copyright  ISB Bayern, 2024
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector extends base_connector {

    #[\Override]
    public function get_models_by_purpose(): array {
        return [
                'imggen' => ['dall-e-3', 'imagen-3.0-generate-002', 'flux-pro', 'flux-pro-max', 'flux.1.1-pro', 'ideogram-generate-v3', 'kling-image', 'kling-virtual_try_on', 'mj_imagine', 'gpt-image-1', 'recraftv3', 'grok-2-image-1212'],
        ];
    }

    #[\Override]
    public function get_prompt_data(string $prompttext, request_options $requestoptions): array {
        $options = $requestoptions->get_options();
        $defaultimagesize = $this->instance->get_model() === 'dall-e-3' ? '256x256' : '1024x1024';
        $parameters = [
                'prompt' => $prompttext,
                'size' => empty($options['sizes'][0]) ? $defaultimagesize : $options['sizes'][0],
                'response_format' => 'b64_json',
        ];
        if (!$this->instance->azure_enabled()) {
            // If azure is enabled, the model will be preconfigured in the azure resource, so we do not need to send it.
            $parameters['model'] = $this->instance->get_model();
        }
        return $parameters;
    }

    #[\Override]
    protected function get_headers(): array {
        $headers = parent::get_headers();
        if (!$this->instance->azure_enabled()) {
            // If azure is not enabled, we just use the default headers for the OpenAI API.
            return $headers;
        }
        if (in_array('Authorization', array_keys($headers))) {
            unset($headers['Authorization']);
            $headers['api-key'] = $this->instance->get_apikey();
        }
        return $headers;
    }

    #[\Override]
    public function get_unit(): unit {
        return unit::COUNT;
    }

    #[\Override]
    public function execute_prompt_completion(StreamInterface $result, request_options $requestoptions): prompt_response {
        global $USER;
        $options = $requestoptions->get_options();
        $content = json_decode($result->getContents(), true);
        $fs = get_file_storage();
        $fileinfo = [
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $options['itemid'],
                'filepath' => '/',
                'filename' => $options['filename'],
        ];
        $file = $fs->create_file_from_string($fileinfo, base64_decode($content['data'][0]['b64_json']));

        $filepath = \moodle_url::make_draftfile_url(
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
        )->out();

        return prompt_response::create_from_result($this->instance->get_model(), new usage(1.0), $filepath);
    }

    #[\Override]
    public function get_available_options(): array {
        $options = [];
        switch ($this->instance->get_model()) {
                case 'dall-e-3':
                case aitool_option_azure::get_azure_model_name('dalle'):
                    // We assume that if using Azure (in which we would have PRECONFIGURED_MODEL as model) we only can deploy dall-e-3.
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1792x1024', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1792px x 1024px)'],
                        ['key' => '1024x1792', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (1024px x 1792px)'],
                    ];
                    break;
                
                case 'imagen-3.0-generate-002':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1536x1024', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1536px x 1024px)'],
                        ['key' => '1024x1536', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (1024px x 1536px)'],
                    ];
                    break;
                
                case 'flux-pro':
                case 'flux-pro-max':
                case 'flux.1.1-pro':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1344x768', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1344px x 768px)'],
                        ['key' => '768x1344', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (768px x 1344px)'],
                        ['key' => '1536x1024', 'displayname' => get_string('wide_landscape', 'local_ai_manager') . ' (1536px x 1024px)'],
                        ['key' => '1024x1536', 'displayname' => get_string('tall_portrait', 'local_ai_manager') . ' (1024px x 1536px)'],
                    ];
                    break;
                
                case 'ideogram-generate-v3':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1280x720', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1280px x 720px)'],
                        ['key' => '720x1280', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (720px x 1280px)'],
                        ['key' => '1440x1024', 'displayname' => get_string('wide_landscape', 'local_ai_manager') . ' (1440px x 1024px)'],
                        ['key' => '1024x1440', 'displayname' => get_string('tall_portrait', 'local_ai_manager') . ' (1024px x 1440px)'],
                    ];
                    break;
                
                case 'kling-image':
                case 'kling-virtual_try_on':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1280x960', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1280px x 960px)'],
                        ['key' => '960x1280', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (960px x 1280px)'],
                    ];
                    break;
                
                case 'mj_imagine':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1456x816', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1456px x 816px)'],
                        ['key' => '816x1456', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (816px x 1456px)'],
                    ];
                    break;
                
                case 'gpt-image-1':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1792x1024', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1792px x 1024px)'],
                        ['key' => '1024x1792', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (1024px x 1792px)'],
                    ];
                    break;
                
                case 'recraftv3':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1365x1024', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1365px x 1024px)'],
                        ['key' => '1024x1365', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (1024px x 1365px)'],
                        ['key' => '1536x1024', 'displayname' => get_string('wide_landscape', 'local_ai_manager') . ' (1536px x 1024px)'],
                        ['key' => '1024x1536', 'displayname' => get_string('tall_portrait', 'local_ai_manager') . ' (1024px x 1536px)'],
                    ];
                    break;
                
                case 'grok-2-image-1212':
                    $options['sizes'] = [
                        ['key' => '1024x1024', 'displayname' => get_string('squared', 'local_ai_manager') . ' (1024px x 1024px)'],
                        ['key' => '1344x768', 'displayname' => get_string('landscape', 'local_ai_manager') . ' (1344px x 768px)'],
                        ['key' => '768x1344', 'displayname' => get_string('portrait', 'local_ai_manager') . ' (768px x 1344px)'],
                    ];
                    break;
            default:
                $options['sizes'] = [];
        }
        return $options;
    }

    #[\Override]
    protected function get_custom_error_message(int $code, ?ClientExceptionInterface $exception = null): string {
        $message = '';
        switch ($code) {
            case 400:
                if (method_exists($exception, 'getResponse') && !empty($exception->getResponse())) {
                    $responsebody = json_decode($exception->getResponse()->getBody()->getContents());
                    if (property_exists($responsebody, 'error') && property_exists($responsebody->error, 'code')
                            && $responsebody->error->code === 'content_policy_violation') {
                        $message = get_string('err_contentpolicyviolation', 'aitool_dalle');
                    }
                }
                break;
        }
        return $message;
    }
}
