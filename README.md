
# GeoNames integration for BardCanvas

This module offers Geo location services for those modules that require it.
It doesn't offer any base utility other than an API and utilities.

## Provided functions

### Maintenance page and importing scripts

After installation, a warning is shown on the top of each page and a link to the maintenance page becomes
available on the menu bar.

The maintenance page shows the status of the information on all the tables, where you can trigger the download
and integration of the data files.

Depending on your server configuration, you may need to wait until the download is done, but if for some reason
you loose connectivity or navigate outside of the page, the download may either continue in the background or
stales. Though we took some precautions, it is impossible to anticiapte to all fail scenarios.

The recommended option is to trigger a download and then wait until it finishes. Time will depend on your
server network/disk speed, processing power and available RAM.

**Note:** the bigger the data table, the longer it takes to download and integrate. Please check the
requirements down below and take them in consideration.

### Classes and methods

*Pending.*

## Requirements

**No data files are included with this module.**

The next tables are downloaded from the GeoNames website:

* **Countries table**: it provides identifiers, geographical coordinates and other details to properly identify
  countries, regions/states and cities.  
  Download size: ~342MB. Needs ~1.5GB of free disk space for decompression/integration into the database.

* **Admin1/Admin2 codes**: these two tables provide region/city relationships with the countries table,
  and it facilitates country > region > city lookups.  
  Download size: ~2.5MB.

* **Alternate names**: multi-language index of entity names for localized lookups.  
  Download size: ~148MB.

* **Postal codes**: index for looking up locations by zip/postal code.  
  Download size: ~14MB.

The next table is downloaded from [DataHub.io's Country Codes repository](https://github.com/datasets/country-codes):

* **Extras**: per-country dial prefixes, currency simbols/names and spoken languages.  
  Download size: < 1MB.

## Licensing compliance

* GeoNames data is licensed using the
  [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/).
  This means **you need to provide acknowledgement and a link to GeoNames** on your website
  (you may add it on the footer and it'll be OK).

* DataHub information is common domain, but if you add a link to GeoNames on the footer, it would
  be nice if you also add a link to [DataHub.io](https://datahub.io/).
