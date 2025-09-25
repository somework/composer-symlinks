ComposerSymlinks
=====================

Its provide a simple Composer script to symlink paths. Compatible with Composer v2.

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

The behaviour of the plugin can be tuned via the following configuration keys:

| Key | Default | Description |
| --- | --- | --- |
| `skip-missing-target` | `false` | Do not fail when the target does not exist. |
| `absolute-path` | `false` | Create symlinks using the real path to the target. |
| `throw-exception` | `true` | Throw an exception on errors instead of just printing the message. |
| `force-create` | `false` | Remove any existing file or directory at the link path before creating the symlink. |

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

### 2. Refresh symlinks

Symlinks are created automatically on `composer install`/`update`, but you can
trigger the process manually with the built-in command:

```bash
$ composer symlinks:refresh
```

Add the `--dry-run` flag to preview the operations without touching the
filesystem:

```bash
$ composer symlinks:refresh --dry-run
```

The legacy environment variable `SYMLINKS_DRY_RUN=1` is still honoured during
Composer hooks for backwards compatibility.

### 3. Execute composer

DO NOT use --no-plugins for composer install or update

### Dry run

Set environment variable `SYMLINKS_DRY_RUN=1` to preview created links without
modifying the filesystem.

Example output:

```bash
$ SYMLINKS_DRY_RUN=1 composer install --no-interaction
  [DRY RUN] Symlinking /tmp/sample/linked.txt to /tmp/sample/source/file.txt
```

### Typical error messages

| Message | Meaning |
|---------|---------|
| `No link passed in config` | The `link` option was missing for a symlink definition. |
| `No target passed in config` | The key of the `symlinks` map was empty. |
| `Invalid symlink target path` | The target path was absolute but should be relative. |
| `Invalid symlink link path` | The link path was absolute but should be relative. |
| `The target path ... does not exists` | The target file or directory was not found. |
| `Link ... already exists` | A file/directory already occupies the link path. |
| `Cant unlink ...` | The plugin failed to remove a file when using `force-create`. |

### Windows compatibility

On Windows, creating symlinks requires either Administrator privileges or that
the system is running in Developer Mode. The plugin itself works, but the
underlying operating system may refuse to create a link if permissions are
insufficient. Additionally, relative symlinks use Unix-style `/` separators
internally which Windows resolves correctly.

License
-------

This component is under the MIT license. See the complete license in the [LICENSE](LICENSE) file.


Reporting an issue or a feature request
---------------------------------------

Issues and feature requests are tracked in the [Github issue tracker](https://github.com/somework/composer-symlinks/issues).
