<?php
namespace App\Services;

class CustomerDataValidator
{
    private const BAD_WORDS = [
        'prueba', 'test', 'testing', 'demo', 'ejemplo', 'falso', 'fake', 'asdf', 'qwerty', 'zxcv',
        'nombre', 'apellido', 'empresa', 'cliente', 'usuario', 'ninguno', 'ninguna', 'n/a', 'na',
        'xxx', 'xxxx', '1234', '4321', 'abcd', 'direccion', 'correo', 'telefono',
    ];

    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org',
        'yopmail.com', 'throwawaymail.com', 'sharklasers.com', 'getnada.com', 'maildrop.cc',
        'dispostable.com', 'trashmail.com', 'fakeinbox.com', 'mailnesia.com', 'spam4.me',
    ];

    public function validate(array $input): array
    {
        $type = strtolower(trim((string)($input['type'] ?? 'natural')));
        if (!in_array($type, ['natural', 'company'], true)) {
            throw new \InvalidArgumentException('Selecciona Persona natural o Persona jurídica.');
        }
        $email = $this->email((string)($input['email'] ?? ''));
        $phone = $this->phone((string)($input['phone'] ?? ''));
        $address = $this->address((string)($input['address'] ?? ''));
        $base = ['type' => $type, 'email' => $email, 'phone' => $phone, 'address' => $address];

        if ($type === 'natural') {
            $name = $this->name((string)($input['full_name'] ?? ($input['name'] ?? '')), 'nombre completo', true, false);
            $document = $this->document((string)($input['document'] ?? ''));
            return array_merge($base, [
                'type' => 'natural',
                'name' => $name,
                'full_name' => $name,
                'document' => $document,
                'display_name' => $name,
            ]);
        }

        $company = $this->name((string)($input['company_name'] ?? ''), 'razón social', false, true, false);
        $nit = $this->nit((string)($input['nit'] ?? ''));
        $contact = $this->name((string)($input['contact_name'] ?? ''), 'nombre del contacto', true, false);
        $position = $this->name((string)($input['position'] ?? ''), 'cargo', false, false, false);
        return array_merge($base, [
            'type' => 'company',
            'name' => $contact,
            'company_name' => $company,
            'nit' => $nit,
            'contact_name' => $contact,
            'position' => $position,
            'display_name' => $company,
        ]);
    }

    private function name(string $value, string $label, bool $twoWords, bool $allowDigits, bool $requireVowel = true): string
    {
        $value = $this->cleanText($value);
        $len = mb_strlen($value);
        if ($len < 2) throw new \InvalidArgumentException('Completa el ' . $label . '.');
        if ($len > ($allowDigits ? 140 : 100)) throw new \InvalidArgumentException('El ' . $label . ' es demasiado largo.');
        if (preg_match('~https?://|www\.|@|<|>~iu', $value)) throw new \InvalidArgumentException('El ' . $label . ' contiene un formato no permitido.');
        $pattern = $allowDigits
            ? '/^[\p{L}\p{N}][\p{L}\p{N}\s\'&.,-]*$/u'
            : '/^[\p{L}][\p{L}\s\'\/.-]*$/u';
        if (!preg_match($pattern, $value)) throw new \InvalidArgumentException('El ' . $label . ' contiene caracteres no permitidos.');
        $this->assertNotGarbage($value, $label, $requireVowel);
        if ($twoWords) {
            $parts = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) < 2) throw new \InvalidArgumentException('Escribe el ' . $label . ' completo.');
        }
        return $value;
    }

    private function document(string $value): string
    {
        if (preg_match('/[a-z]/i', $value)) {
            throw new \InvalidArgumentException('El número de documento solo puede contener dígitos.');
        }
        $value = preg_replace('/[\s.-]+/', '', trim($value)) ?? '';
        if ($value === '' || strlen($value) < 5 || strlen($value) > 15 || !ctype_digit($value)) {
            throw new \InvalidArgumentException('Escribe un número de documento válido, solo dígitos (5 a 15).');
        }
        if ($this->isRepeated($value) || $this->isSequential($value) || $value === '00000000') {
            throw new \InvalidArgumentException('El número de documento no parece válido.');
        }
        return $value;
    }

    private function nit(string $value): string
    {
        if (preg_match('/[a-z]/i', $value)) {
            throw new \InvalidArgumentException('El NIT solo puede contener dígitos.');
        }
        $value = preg_replace('/[\s.]+/', '', trim($value)) ?? '';
        $value = str_replace('-', '', $value);
        if (!ctype_digit($value) || strlen($value) < 9 || strlen($value) > 10) {
            throw new \InvalidArgumentException('Escribe un NIT válido de 9 o 10 dígitos.');
        }
        if ($this->isRepeated($value) || $this->isSequential($value)) {
            throw new \InvalidArgumentException('El NIT no parece válido.');
        }
        $base = substr($value, 0, -1);
        $check = (int)substr($value, -1);
        $weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $sum = 0;
        $pos = 0;
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $sum += (int)$base[$i] * $weights[$pos++];
        }
        $mod = $sum % 11;
        $dv = $mod > 1 ? 11 - $mod : $mod;
        if ($dv !== $check) throw new \InvalidArgumentException('El dígito de verificación del NIT no es válido.');
        return $value;
    }

    private function email(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Escribe un correo electrónico válido.');
        }
        if (preg_match('/\.\./', $value) || preg_match('/[<>(),;:\\\\"]/', $value)) {
            throw new \InvalidArgumentException('El correo contiene un formato no permitido.');
        }
        $parts = explode('@', $value);
        if (count($parts) !== 2) throw new \InvalidArgumentException('Escribe un correo electrónico válido.');
        [$local, $domain] = $parts;
        if (strlen($local) < 3 || strpos($domain, '.') === false) throw new \InvalidArgumentException('Escribe un correo electrónico válido.');
        $tld = substr($domain, strrpos($domain, '.') + 1);
        if (strlen($tld) < 2 || !preg_match('/^[a-z]{2,}$/', $tld)) throw new \InvalidArgumentException('El dominio del correo no es válido.');
        if (in_array($domain, self::DISPOSABLE, true) || str_contains($local, 'test') || str_contains($domain, 'example')) {
            throw new \InvalidArgumentException('Usa un correo personal o empresarial real.');
        }
        return $value;
    }

    private function phone(string $value): string
    {
        if (preg_match('/[a-z]/i', $value)) {
            throw new \InvalidArgumentException('El teléfono solo puede contener números y separadores.');
        }
        $value = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (strlen($value) === 12 && str_starts_with($value, '57')) $value = substr($value, 2);
        if (strlen($value) !== 10 || !ctype_digit($value)) throw new \InvalidArgumentException('Escribe un teléfono de 10 dígitos.');
        if (!preg_match('/^(3|60)/', $value)) throw new \InvalidArgumentException('Escribe un teléfono de contacto válido.');
        if ($this->isRepeated($value) || $this->isSequential($value) || $value === '3000000000') {
            throw new \InvalidArgumentException('El teléfono no parece válido.');
        }
        return $value;
    }

    private function address(string $value): string
    {
        $value = $this->cleanText($value);
        if ($value === '') return '';
        if (mb_strlen($value) < 5 || mb_strlen($value) > 200) throw new \InvalidArgumentException('La dirección debe tener entre 5 y 200 caracteres.');
        if (preg_match('~https?://|www\.|<|>~iu', $value)) throw new \InvalidArgumentException('La dirección contiene un formato no permitido.');
        $letters = preg_replace('/[^\p{L}]/u', '', $value) ?? '';
        if (mb_strlen($letters) < 3) throw new \InvalidArgumentException('Escribe una dirección válida.');
        $this->assertNotGarbage($value, 'dirección', false);
        return $value;
    }

    private function assertNotGarbage(string $value, string $label, bool $requireVowel): void
    {
        $norm = $this->fold($value);
        if ($norm === '') throw new \InvalidArgumentException('Completa el ' . $label . '.');
        if (preg_match('/(.)\1{3,}/u', $norm)) throw new \InvalidArgumentException('El ' . $label . ' contiene texto repetido.');
        foreach (self::BAD_WORDS as $bad) {
            if ($norm === $bad || preg_match('/(^|\s)' . preg_quote($bad, '/') . '(\s|$)/u', $norm)) {
                throw new \InvalidArgumentException('Escribe un dato real en el ' . $label . '.');
            }
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $norm) ?? '';
        if (mb_strlen($letters) < 2) throw new \InvalidArgumentException('Escribe un dato real en el ' . $label . '.');
        if ($requireVowel && !preg_match('/[aeiou]/', $letters)) throw new \InvalidArgumentException('El ' . $label . ' no parece válido.');
        if (preg_match('/qwerty|asdf|zxcv|1234|4321|9876|abcd/', $norm)) throw new \InvalidArgumentException('El ' . $label . ' no parece válido.');
        if (mb_strlen($letters) >= 8) {
            $chars = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $unique = count(array_unique($chars));
            if ($unique / max(1, count($chars)) < 0.35) throw new \InvalidArgumentException('El ' . $label . ' no parece válido.');
        }
    }

    private function cleanText(string $value): string
    {
        $value = strip_tags(trim($value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    private function isRepeated(string $value): bool
    {
        return (bool)preg_match('/^(\d)\1+$/', $value);
    }

    private function isSequential(string $value): bool
    {
        $asc = '01234567890123456789';
        $desc = '98765432109876543210';
        return str_contains($asc, $value) || str_contains($desc, $value);
    }
}
