# org.unidosnow.unutils
![UnidosNow](/images/UN-Badge.png)

CiviCRM utilities extension for UnidosNow.org operations.

Initially created in HostGator folder dev-unidosnow-co/sites/default/files/civicrm/ext with command "ea-php72 ~/bin/civix generate:module org.unidosnow.unutils --license=AGPL-3.0 --email=it@unidosnow.org"

For the first use case, inside the module folder, "ea-php72 ~/bin/civix generate:api unjob hhgroups --schedule Daily"

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM (5.27)

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl org.unidosnow.utils@https://github.com/HikerBoricua/org.unidosnow.utils/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/HikerBoricua/org.unidosnow.utils.git
cv en utils
```

## Usage

See README.md in api/v3/Job/

## Known Issues

TBD

