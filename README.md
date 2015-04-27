(Drupal) Packagist
=========

This is a hacked up fork of packagist for use with Drupal. The forking and
hacking was done for want of a fast way to experiment with the problem domain
while developing the Drupal-specific functionality separately.

All things considered, it would be best to provide separate repositories for the
following:

* The Packagist/WebBundle by itself
* HA functionality -- mostly the queueing used to bootstrap by traversing all
  the drupal.org project repos with worker nodes
* Drupal-specific applications for the above
* Drupal CLI tools for parsing update and release info as a thing unto itself

Instead, we have added the queuing and Drupal-specific functionality in place.
The main workflow so far has been to install the application as normal and then
populate the database so that you can generate a composer repository like so:

```
./app/console packagist:bulk_add --repo-pattern \
'http://git.drupal.org/project/%2$s' --vendor drupal $(curl \
https://drupal.org/files/releases.tsv | grep 7.x | awk '{ print $3 }' | sort | uniq -)
```

Running 10 AWS c3.large instances to consume the queue filled by the
`packagist:bulk_add` command allows the process to complete in a few hours.

Experimental support for automatic updates has been added in the form of
a foreground package upsert command that gets invoked by the
`packagist:drupal_org_update` command, which parses the drupal 7 new releases
rss feed. You would need to invoke this command with cron or similar to keep the
application up to date with drupal.org and you would need to monitor disk space
since the package information is read by cloning a bare repo from drupal.org and
never removing it. You could consider updating the `drupal/parse_composer`
project to add an appropriate cleanup method to the Repository class there, or
just sweep out the cache directory composer uses at the end of the cron job.

Unfortunately, the rss feed references the projects by drupal module name, which
is always snake_case, but the repo URL is case sensitive and therefore stupid
project names with uppercase letters will cause things to break. The only
obvious workaround would be to periodically run through the releases.tsv. In
limited sampling, only useless modules had this problem.

Package Repository Website for Composer, see the [about page](http://packagist.org/about) on [packagist.org](http://packagist.org/) for more.

Requirements
------------

- MySQL for the main data store
- Redis for some functionality (favorites, download statistics)
- Solr for search
- git/svn/hg depending on which repositories you want to support

Installation
------------

1. Clone the repository
2. Copy `app/config/parameters.yml.dist` to `app/config/parameters.yml` and edit the relevant values for your setup.
3. Install dependencies: `php composer.phar install`
4. Run `app/console doctrine:schema:create` to setup the DB.
5. Run `app/console assets:install web` to deploy the assets on the web dir.
6. Make a VirtualHost with DocumentRoot pointing to web/

You should now be able to access the site, create a user, etc.

Setting up search
-----------------

The search index uses [Solr](http://lucene.apache.org/solr/) 3.6, so you will have to install that on your server.
If you are running it on a non-standard host or port, you will have to adjust the configuration. See the
[NelmioSolariumBundle](https://github.com/nelmio/NelmioSolariumBundle) for more details.

You will also have to configure Solr. Use the `schema.xml` provided in the doc/ directory for that.

To index packages, just run `app/console packagist:index`. It is recommended to set up a cron job for
this command, and have it run every few minutes.

Day-to-Day Operation
--------------------

There are a few commands you should run periodically (ideally set up a cron job running every minute or so):

    app/console packagist:update --no-debug --env=prod
    app/console packagist:dump --no-debug --env=prod
    app/console packagist:index --no-debug --env=prod

The latter is optional and only required if you are running a solr server.

Development: Frontend
---------------------

[Grunt](http://gruntjs.com/) is used for processing frontend styles in
development (mainly generating css from sass) for the DrupalPackagist Bundle.

- Install node/npm/grunt
- `npm install`
- `grunt` (will watch for changes in scss)
