# Drupal 10 Starter

The Drupal 10 Starter repository is meant to provide a quick and easy development
environment for working on projects locally. The typical process for using Lando
is to fork the project and use it as a base, modifying and adjusting it to meet
your needs.

This starter ships with a number of utilities that can be accessed and used
inside the containers by running them with the prefix `lando`. For instance:

```
# Install composer dependencies
lando composer install

# Rebuild the Drupal cache with Drush
lando drush cr
```

One of the tools provided is the Robo task runner for PHP.
The project comes with a variety of example Robo tasks in the `RoboFile.php`
file.

## Local Development

### Dependencies

  - [Docker](https://docs.docker.com/get-docker)
  - [Lando](https://docs.lando.dev/basics/installation.html)

### Installation Steps

  2. Clone the repository. Eg. `git clone path-to-repo drupal10`
  2. `cd drupal10`
  4. Edit lando project name with `sed -i 's/drupal10-starter/drupal-my-site/g' .lando.yml` OSX `sed -i '' 's/drupal10-starter/drupal-site/g' .lando.yml`
  4. `lando start`
  5. `lando robo local:init drupal10_site`

### Run Behat tests.

All the dependencies needed to run functional tests via Behat are already
installed on this project, to run one of the examples test do the following:

`lando behat features/visitor_homepage.feature`

If you want to know the available Behat steps then run:

`lando behat -dl`

More information about Behat Drupal Extension can be found at https://behat-drupal-extension.readthedocs.io/en/v4.0.1/index.html

### Making use of phpstan for static analysis

To run `phpstan` use the robo command: `lando robo analyse` which will run `phpstan` on the codebase, excluding common
locations for third party code, and return a list of errors and suggestions for code improvement.

If you are introducing `phpstan` into an existing codebase and initially only want to analyse new code going forward
until technical debt can be addressed, run the `lando robo analyse:baseline` command to record all existing issues into
a `phpstan-baseline.neon` file. Then add this file to the includes section of `phpstan.neon.dist`.

Documentation for `phpstan` can be found at https://phpstan.org/.

### Bitbucket pipelines

This project have the following pipelines:

- Drupal Coding Standards
- Unit/Kernel tests
- Functional tests via Behat

Those things run on each merge request, but you need to follow a naming convention
for your branches.

- Feature branches - feature/name-of-feature
- Bugfix branches - fix/bug-name
