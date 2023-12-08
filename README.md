# <img align="right" src="https://github.com/openvk/openvk/raw/master/Web/static/img/logo_shadow.png" alt="openvk" title="openvk" width="15%">OpenVK

_[Русский](README_RU.md)_

**OpenVK** is an attempt to create a simple CMS that ~~cosplays~~ imitates old VKontakte. Code provided here is not stable yet.

VKontakte belongs to Pavel Durov and VK Group.

To be honest, we don't know whether if it even works. However, this version is maintained and we will be happy to accept your bugreports [in our bug tracker](https://github.com/openvk/openvk/projects/1). You should also be able to submit them using [ticketing system](https://openvk.su/support?act=new) (you will need an OpenVK account for this).

## When's the release?

We will release OpenVK as soon as it's ready. As for now, you can:
* `git clone` this repo's master branch (use `git pull` to update)
* Grab a prebuilt OpenVK distro from [GitHub artifacts](https://nightly.link/openvk/archive/workflows/nightly/master/OpenVK%20Archive.zip)

## Instances

A list of instances can be found in [our wiki of this repository](https://github.com/openvk/openvk/wiki/Instances).

## Can I create my own OpenVK instance?

Yes! And you are very welcome to.

However, OVK makes use of Chandler Application Server. This software requires extensions, that may not be provided by your hosting provider (namely, sodium and yaml. these extensions are available on most of ISPManager hostings).

If you want, you can add your instance to the list above so that people can register there.

### Installation procedure

1. Install PHP 7.4, web-server, Composer, Node.js, Yarn and [Chandler](https://github.com/openvk/chandler)

* PHP 8 is still being tested; the functionality of the engine on this version of PHP is not yet guaranteed.

2. Install MySQL-compatible database.

* We recommend using Percona Server, but any MySQL-compatible server should work too.
* Server should be compatible with at least MySQL 5.6, MySQL 8.0+ is recommended.
* Support for MySQL 4.1+ is WIP, replace `utf8mb4` and `utf8mb4_unicode_520_ci` with `utf8` and `utf8_unicode_ci` in SQLs.

3. Install [commitcaptcha](https://github.com/openvk/commitcaptcha) and OpenVK as Chandler extensions like this:

```bash
git clone https://github.com/openvk/openvk /path/to/chandler/extensions/available/openvk
git clone https://github.com/openvk/commitcaptcha /path/to/chandler/extensions/available/commitcaptcha
```

4. And enable them:

```bash
ln -s /path/to/chandler/extensions/available/commitcaptcha /path/to/chandler/extensions/enabled/
ln -s /path/to/chandler/extensions/available/openvk /path/to/chandler/extensions/enabled/
```

5. Import `install/init-static-db.sql` to the **same database** you installed Chandler to and import all sqls from `install/sqls` to the **same database**
6. Import `install/init-event-db.sql` to a **separate database** (Yandex.Clickhouse can also be used, highly recommended)
7. Copy `openvk-example.yml` to `openvk.yml` and change options to your liking
8. Run `composer install` in OpenVK directory
9. Run `composer install` in commitcaptcha directory
10. Move to `Web/static/js` and execute `yarn install`
11. Set `openvk` as your root app in `chandler.yml`

Once you are done, you can login as a system administrator on the network itself (no registration required):

* **Login**: `admin@localhost.localdomain6`
* **Password**: `admin`
  * It is recommended to change the password of the built-in account or disable it.

💡Confused? Full installation walkthrough is available [here](https://docs.openvk.uk/openvk_engine/centos8_installation/) (CentOS 8 [and](https://almalinux.org/) [family](https://yum.oracle.com/oracle-linux-isos.html)).

### Looking for Docker or Kubernetes deployment?
See `install/automated/docker/README.md` and `install/automated/kubernetes/README.md` for Docker and Kubernetes deployment instructions.

### If my website uses OpenVK, should I release it's sources?

It depends. You can keep the sources to yourself if you do not plan to distribute your website binaries. If your website software must be distributed, it can stay non-OSS provided the OpenVK is not used as a primary application and is not modified. If you modified OpenVK for your needs or your work is based on it and you are planning to redistribute this, then you should license it under terms of any LGPL-compatible license (like OSL, GPL, LGPL etc).

## Where can I get assistance?

You may reach out to us via:

* [Bug Tracker](https://github.com/openvk/openvk/projects/1)
* [Ticketing System](https://openvk.su/support?act=new)
* Telegram Chat: Go to [our channel](https://t.me/openvkenglish) and open discussion in our channel menu.
* [Reddit](https://www.reddit.com/r/openvk/)
* [GitHub Discussions](https://github.com/openvk/openvk/discussions)
* Matrix Chat: #openvk:matrix.org

**Attention**: bug tracker, board, Telegram and Matrix chat are public places, ticketing system is being served by volunteers. If you need to report something that should not be immediately disclosed to general public (for instance, a vulnerability), please contact us directly via this email: **openvk [at] tutanota [dot] com**

<a href="https://codeberg.org/OpenVK/openvk">
    <img alt="Get it on Codeberg" src="https://codeberg.org/Codeberg/GetItOnCodeberg/media/branch/main/get-it-on-blue-on-white.png" height="60">
</a>
