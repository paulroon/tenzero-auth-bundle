# User class

Create a User entity that extends the bundle base class and maps the identifier field.

Example:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Happycode\TenZeroAuth\Model\TenZeroUser;

#[ORM\Entity]
class User extends TenZeroUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
```

Notes:
- Set `user_class` to `App\\Entity\\User` and `user_field` to `email`.
- Add any extra fields as needed; the bundle already provides password/roles fields via the mapped superclass.
