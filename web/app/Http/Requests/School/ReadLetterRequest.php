<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * A letter for the AI to read: a PDF, a screenshot/photo, or pasted text (or a file plus text).
 * Claude reads PNG, JPEG, WebP, GIF and PDF. iPhone camera photos (HEIC) are not supported.
 */
class ReadLetterRequest extends FormRequest
{
    private const IMAGE_MAX_KB = 5 * 1024;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'required_without:text', 'file', 'max:10240',
                'mimetypes:application/pdf,image/png,image/jpeg,image/webp,image/gif'],
            'text' => ['nullable', 'required_without:file', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Add a PDF or screenshot, or paste the message text.',
            'text.required_without' => 'Add a PDF or screenshot, or paste the message text.',
            'file.mimetypes' => 'Use a PDF, PNG, JPEG or WebP. iPhone photos (HEIC): take a screenshot of the photo instead.',
            'file.max' => 'The file is too big (10 MB max).',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('file');

            if ($file instanceof UploadedFile && str_starts_with((string) $file->getMimeType(), 'image/')
                && $file->getSize() > self::IMAGE_MAX_KB * 1024) {
                $validator->errors()->add('file', 'Images can be 5 MB at most. A screenshot is usually much smaller.');
            }
        }];
    }
}
