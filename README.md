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

Create the symlinks definition adding a `somework/composer-symlinks` section inside the `extra` section of the composer.json file.

Set `skip-missing-target` to true if we should not throw exception if target path doesn't exists  
Set `absolute-path` to true if you want to create realpath symlinks  
Set `throw-exception` to false if you dont want to break creating on some error while check symlinks  
Set `force-create` to force unlink link if something already exists on link path    

You can set personal configs for any symlink.  
For personal configs `link` must be defined  

```json
{
    "extra": {
        "somework/composer-symlinks": {
            "symlinks": {
                "common/upload": "web/upload",
                "common/static/dest": {
                    "link": "web/dest",
                    "skip-missing-target": false,
                    "absolute-path": true,
                    "throw-exception": false
                }
            },
            "force-create": false,
            "skip-missing-target": false,
            "absolute-path": false,
            "throw-exception": true
        }
    }
}
```

### 3. Execute composer

DO NOT use --no-plugins for composer install or update

License
-------

This component is under the MIT license. See the complete license in the [LICENSE] file.


Reporting an issue or a feature request
---------------------------------------

Issues and feature requests are tracked in the [Github issue tracker].
