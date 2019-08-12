# Cache Warmup for TYPO3

This extension provides a simple command line tool for warming up
certain caches.

This extension is especially useful for large installations that deploy and
then flush caches.

The current state is provided as of TYPO3 v8 LTS, and handles
the Rootline Cache.


## Rootline Cache
One of the main issues here is a large installation with lots of
pages. A `cache:flush` via TYPO3 console will empty the information
on the rootline cache. When a visitor visits the page again after
flushing the cache, and the page has 100 links to other pages, the
rootline for each of the page will be built. This could take
several seconds. A second visitor could visit the page and then
see the nice "Page is being generated" screen. This can be improved!

A command line script runs directly after `cache:flush` and warms
up all caches. This is mostly `cache_core` (by running the script
itself) and `cache_rootline`.

```
./typo3/sysext/core/bin/typo3 cache:warmup
```

Running the script multiple times does not matter, as it solely
acts as a wrapper for fetching the rootline for a page. If it is
already in the cache, the command runs smoothly.

## Installation

Install the extension by extracting the contents of this folder into
typo3conf/ext/warmup and install the extension via the Extension Manager.

Alternatively, you can use composer via `composer req cmsexperts/warmup`.


## Notes

Note that this extension does not take workspaces or mount points
into account currently! Contributions are welcome.


## Credits

* Benni Mack


## License

GPL2.0+, see LICENSE.txt for more details.
