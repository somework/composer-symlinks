ComposerSymlinks
=====================

Its provide a simple Composer script to symlink paths.

Installation
------------

To install the latest stable version of this component, open a console and execute the following command:

```
$ composer require somework/composer-symlinks
```

Usage
-----

### 1. Define symlinks

Create the symlinks definition adding a `somework/composer-symlinks` section inside the `extra` section of the composer.json file:
```json
{
    "extra": {
        "somework/composer-symlinks": {
            "common/upload": "web/upload",
            "common/static/dest": "web/dest"
        }
    }
}
```

### 3. Hook the script to composer events

Add a new script definition to the `scripts` section of the composer.json file, so the symlinks are created after
packages installation or update:
```json
{
    "scripts": {
        "post-install-cmd": [
            "SomeWork\\Composer\\Symlinks::create"
        ],
        "post-update-cmd": [
            "SomeWork\\Composer\\Symlinks::create"
        ]
    }
}
```

### 4. Execute composer

License
-------

This component is under the MIT license. See the complete license in the [LICENSE] file.


Reporting an issue or a feature request
---------------------------------------

Issues and feature requests are tracked in the [Github issue tracker].
