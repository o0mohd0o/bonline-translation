# Bonlineco Translation Module for Magento 2

## Overview
The Bonlineco Translation module enhances Magento 2's translation capabilities by providing a user-friendly interface in the admin panel to manage, import, export, and deploy translations. This simplifies the process of customizing translations for your store without modifying CSV files directly.

## Features

### Translation Management
- **View and Search Translations**: Easily browse, search, and filter translations by locale and store view.
- **Add/Edit Translations**: Create new translations or modify existing ones through an intuitive interface.
- **Delete Translations**: Remove unwanted translations individually or in bulk.
- **Mass Actions**: Select multiple translations to delete in bulk.

### Import/Export
- **Import Translations**: Upload CSV files with translations to quickly add multiple translations at once.
- **Export Translations**: Export translations to CSV files for backup or editing in a spreadsheet application.

### Deployment and Cache Management
- **Deploy Custom Translations**: Deploy custom translations from the database to Magento's translation files.
- **Clean Cache**: Clear Magento's translation cache directly from the admin panel.
- **Deploy Static Content**: Run the static content deployment process to apply translation changes.

## Screenshots

### Translation Management
![Translation Management Interface](https://bonlineco.com/github/bonlineco-translation/bonlineco-translation-manage-translation.png)

### Translation Tools
![Translation Tools Interface](https://bonlineco.com/github/bonlineco-translation/bonlineco-translation-translation-tools.png)

## Installation

### Manual Installation
1. Download the module archive
2. Extract the contents to `app/code/Bonlineco/Translation/` directory
3. Run the following commands from the Magento root directory:
   ```
   bin/magento module:enable Bonlineco_Translation
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy
   ```

### Using Composer
```
composer require bonlineco/module-translation
bin/magento module:enable Bonlineco_Translation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

## Usage

### Managing Translations
1. Navigate to **Bonline > Translation > Manage Translations**
2. Use the search field, locale, and store filters to find specific translations
3. Click **Add New Translation** to create a new translation
4. Click **Edit** on an existing translation to modify it
5. Select multiple translations and use the mass actions dropdown to delete them in bulk

### Importing Translations
1. Navigate to **Bonline > Translation > Manage Translations**
2. Click **Import Translations**
3. Select a CSV file with columns: string, translation, locale, store_id
4. Click **Import**

### Exporting Translations
1. Navigate to **Bonline > Translation > Manage Translations**
2. Click **Export Translations**
3. Select a locale (optional) to export translations for a specific language
4. Click **Export**

### Deploying Translations
1. Navigate to **Bonline > Translation > Translation Tools**
2. Click **Deploy Custom Translations** to apply your custom translations
3. Use **Clean Translation Cache** and **Deploy Static Content** as needed after making changes

## CSV Format for Import
The CSV file for importing translations should have the following format:

```
string,translation,locale,store_id
"Original text","Translated text","en_US","0"
```

- **string**: The original text to be translated
- **translation**: The translated text
- **locale**: The locale code (e.g., en_US, fr_FR)
- **store_id**: The store ID (0 for all stores or a specific store ID)

## Permissions
This module adds the following permissions:
- `Bonlineco_Translation::manage` - Allows managing translations
- `Bonlineco_Translation::tools` - Allows access to translation tools

## Compatibility
- Magento 2.3.x
- Magento 2.4.x

## Support
For issues or questions, please contact the Bonline support team or submit an issue in the repository.
info@bonlineco.com

## License
This module is licensed under the [MIT License](https://opensource.org/licenses/MIT).
