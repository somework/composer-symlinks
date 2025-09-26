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
| `cleanup` | `false` | Remove symlinks that were created previously but are no longer present in the configuration. |
| `windows-mode` | `junction` | Windows-only strategy: `symlink` (require Developer Mode/administrator), `junction` (fallback to junction/hardlink/copy), or `copy` (mirror the target without links). |

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
            "throw-exception": true,
            "cleanup": true
        }
    }
}
```

When `cleanup` is enabled the plugin keeps a registry of every symlink it
created in the file `vendor/composer-symlinks-state.json`. On the next run the
registry is compared with the current configuration: entries missing from the
configuration are deleted from both the registry and the filesystem. The file
is recreated automatically and can safely be ignored by VCS.

### Placeholder syntax

Symlink paths support the following placeholders which are expanded before
validation:

| Placeholder | Description |
|-------------|-------------|
| `%project-dir%` | Absolute path to the project root (current working directory during Composer execution). |
| `%vendor-dir%` | Absolute path to the Composer vendor directory. |
| `%env(NAME)%` | Value of the environment variable `NAME`. Missing variables expand to an empty string. |

Placeholders allow the resulting paths to be absolute while the original
configuration remains portable. Direct absolute paths without placeholders are
still rejected by default to avoid accidental misuse.

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

### 3. Inspect symlink status

Audit the current state of the configured links without modifying the
filesystem:

```bash
$ composer symlinks:status
```

The command compares the configuration with the registry file and the actual
filesystem. The report highlights missing links, mismatched targets, and stale
entries that are present in the registry but no longer configured. Use the
`--strict` option to make the command exit with a non-zero code when problems
are detected (ideal for CI pipelines), and `--json` to obtain a machine-readable
summary:

```bash
$ composer symlinks:status --json --strict
```

### 4. Execute composer

DO NOT use --no-plugins for composer install or update

### Dry run

Set environment variable `SYMLINKS_DRY_RUN=1` to preview created links without
modifying the filesystem.

Example output:

```bash
$ SYMLINKS_DRY_RUN=1 composer install --no-interaction
  [DRY RUN] Symlinking /tmp/sample/linked.txt to /tmp/sample/source/file.txt
```

### Uninstalling

Running `composer remove somework/composer-symlinks` will trigger the plugin's
`uninstall()` hook. The hook reads the registry file and removes every stored
symlink from the filesystem before deleting the registry itself. This ensures
that no links created by the plugin are left behind when the package is
removed.

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
| `Failed to create symlink ... Enable Windows Developer Mode ...` | Windows denied symlink creation. Enable Developer Mode or set `windows-mode` to `junction`/`copy`. |

Run `composer symlinks:status` after encountering an error to obtain a detailed
report of which links are missing or pointing to unexpected targets.

### Windows compatibility

On Windows, creating symlinks requires either Administrator privileges or that
the system is running in Developer Mode. When native symlinks are not
available, the plugin falls back automatically (default `windows-mode` value)
to NTFS junctions for directories and hardlinks/copies for files. You can opt
into different behaviours by setting `extra.somework/composer-symlinks.windows-mode`
to one of:

* `symlink` – always attempt a real symlink. Failures explicitly mention
  enabling Developer Mode or switching to a fallback strategy.
* `junction` (default) – try a symlink first, then use junctions for directories
  and hardlinks/copies for files when permissions prevent symlink creation.
* `copy` – skip symlinks altogether and mirror the target contents at the link
  path.

Regardless of the chosen mode, relative symlinks use Unix-style `/` separators
internally which Windows resolves correctly.

License
-------

This component is under the MIT license. See the complete license in the [LICENSE](LICENSE) file.


Reporting an issue or a feature request
---------------------------------------

Issues and feature requests are tracked in the [Github issue tracker](https://github.com/somework/composer-symlinks/issues).
