# Open Course Catalogue API client

Drupal implementation of an Open Course Catalogue API client.

## Installation

Include the repository in your project's `composer.json` file:

    "repositories": [
        ...
        {
            "type": "vcs",
            "url": "https://github.com/EuropeanUniversityFoundation/occapi_client"
        }
    ],

Then you can require the package as usual:

    composer require euf/occapi_client

Finally, install the module:

    drush en occapi_client

## Usage

The OCCAPI client settings will be available at `/admin/config/services/occapi`. The corresponding permissions are grouped under _OCCAPI client_ at `/admin/people/permissions`.
