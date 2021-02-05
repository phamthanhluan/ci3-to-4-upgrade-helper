<?php

declare(strict_types=1);

/*
 * Copyright (c) 2021 Kenji Suzuki
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/kenjis/ci3-to-4-upgrade-helper
 */

namespace Kenjis\CI3Compatible\Library;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Services;
use Kenjis\CI3Compatible\Exception\NotImplementedException;
use Kenjis\CI3Compatible\Library\Upload\ValidationRuleMaker;

use function strlen;
use function substr;

class CI_Upload
{
    /** @var ValidationRuleMaker */
    private $ruleMaker;

    /** @var array */
    private $ci3Config;

    /** @var UploadedFile|null */
    private $file;

    /**
     * Constructor
     *
     * @param   array $config
     *
     * @return  void
     */
    public function __construct(array $config = [])
    {
        $this->ci3Config = $config;
        $this->ruleMaker = new ValidationRuleMaker();

        $this->checkNotImplementedConfig();
    }

    private function checkNotImplementedConfig()
    {
        $notImplemented = [
            'file_name',
            'file_ext_tolower',
            'overwrite',
            'max_filename',
            'max_filename_increment',
            'remove_spaces',
            'detect_mime',
            'mod_mime_fix',
        ];

        foreach ($notImplemented as $item) {
            if (isset($this->ci3Config[$item])) {
                throw new NotImplementedException(
                    'config "' . $item . '" is not implemented yet.'
                );
            }
        }
    }

    /**
     * Perform the file upload
     *
     * @param   string $field
     *
     * @return  bool
     */
    public function do_upload(string $field = 'userfile')
    {
        $validation = Services::validation();
        $request = Services::request();

        $rules = $this->ruleMaker->convert($field, $this->ci3Config);
        $isValid = $validation->withRequest($request)->setRules($rules)->run();

        if (! $isValid) {
            return false;
        }

        $this->file = $request->getFile($field);

        if ($this->file !== null) {
            if ($this->file->isValid() && ! $this->file->hasMoved()) {
                if ($this->ci3Config['encrypt_name']) {
                    $newName = $this->file->getRandomName();
                    $this->file->move($this->ci3Config['upload_path'], $newName);
                } else {
                    $this->file->move($this->ci3Config['upload_path']);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Finalized Data Array
     *
     * Returns an associative array containing all of the information
     * related to the upload, allowing the developer easy access in one array.
     *
     * @param   string $index
     *
     * @return  mixed
     */
    public function data(?string $index = null)
    {
        $full_path = $this->ci3Config['upload_path'] . '/' . $this->file->getName();
        $raw_name = substr(
            $this->file->getName(),
            0,
            -strlen($this->file->getClientExtension())
        );

        $data = [
            'file_name'      => $this->file->getName(),
            'file_type'      => $this->file->getClientMimeType(),
            'file_path'      => $this->ci3Config['upload_path'],
            'full_path'      => $full_path,
            'raw_name'       => $raw_name,
            'orig_name'      => $this->file->getClientName(),
            'client_name'    => $this->file->getClientName(),
            'file_ext'       => $this->file->getClientExtension(),
            'file_size'      => $this->file->getSize(),
// @TODO
//            'is_image'       => $this->is_image(),
//            'image_width'    => $this->image_width,
//            'image_height'   => $this->image_height,
//            'image_type'     => $this->image_type,
//            'image_size_str' => $this->image_size_str,
        ];

        if (! empty($index)) {
            return $data[$index] ?? null;
        }

        return $data;
    }
}
