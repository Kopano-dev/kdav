Name:       kopano-kdav
Version:    0.9
Release:    1%{?dist}
Summary:    CalDAV and CardDAV implementation for Kopano 8.6.2 or newer.
License:    AGPL-3.0
BuildArch:  noarch
URL:        https://github.com/Kopano-dev/kdav
Source:     %name-%version.tar.gz
BuildRoot:  %_tmppath/%name-%version-build
Requires: php >= 5.4.0
Requires: php-xml
Requires: php-mbstring
Requires: php-mapi-webapp

%description
kdav is CalDAV and CardDAV implementation for Kopano 8.6.2 or newer.

%define kdav_data_dir %_datadir/kopano-kdav
%define kdav_conf_dir %_sysconfdir/kopano/kdav
%define apache_dir %_sysconfdir/httpd
%define nginx_dir %_sysconfdir/nginx


# Package to use with apache
%package -n %name-apache
Summary:    Kopano kdav for apache webserver
Requires:   httpd
Provides:   %name
%description -n %name-apache
kdav is CalDAV and CardDAV implementation for Kopano 8.6.2 or newer. Uses apache as a webserver.

# Package to use with nginx
%package -n %name-nginx
Summary:    Kopano kdav for nginx webserver
Requires:   nginx
%description -n %name-nginx
kdav is CalDAV and CardDAV implementation for Kopano 8.6.2 or newer. Uses nginx as a webserver.

%prep
%setup -q

%build

%install
b="%buildroot";
cdir="$b/%kdav_conf_dir";

mkdir -p "$b/%kdav_data_dir"
cp -a lib/* "$b/%kdav_data_dir/"
cp -a mapi/* "$b/%kdav_data_dir/"

# COMMON
# set version number
sed -s "s/KDAVVERSION/%version/" build/version.php.in > "$b/%kdav_data_dir/version.php"

mkdir -p "$cdir"

mv "$b/%kdav_data_dir/config.php" "$cdir/kdav.conf.php"
ln -s "%kdav_conf_dir/kdav.conf.php" "$b/%kdav_data_dir/config.php"

# LOGROTATE
mkdir -p "$b/%_sysconfdir/logrotate.d"
install -Dpm 644 config/kdav-rhel.lr "$b/%_sysconfdir/logrotate.d/kdav.lr"

# COMMON
%files
%exclude %kdav_data_dir/
%defattr(-, root, root)
%dir %_sysconfdir/kopano
%dir %_sysconfdir/kopano/kdav
%dir kdav_data_dir/
%config(noreplace) %attr(0640,root,root) %kdav_conf_dir/kdav.conf.php
%config(noreplace) %attr(0640,root,root) %_sysconfdir/logrotate.d/kdav.lr
%doc LICENSE
%doc TRADEMARKS

%post
# COMPOSER
cd %kdav_data_dir/
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'e0012edf3e80b6978849f5eff0d4b4e4c79ff1609dd1e613307e16318854d24ae64f26d17af3ef0bf7cfb710ca74755a') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
./composer.phar install
rm ./composer.phar
cd -

# APACHE
%post -n %name-apache
mkdir -p "$b/%apache_dir/conf.d"
install -Dpm 644 config/apache/kdav.conf "$b/%apache_dir/conf.d/kdav.conf"
service httpd reload || true

# NGINX
%post -n %name-nginx
mkdir -p "$b/%nginx_dir/sites-available/"
install -Dpm 644 config/nginx/kdav.conf "$b/%_nginx_dir/sites-available/kdav.conf"
ln -s %nginx_dir/sites-available/kdav.conf %nginx_dir/sites-enabled/kdav.conf
service httpd reload || true

%postun -n %name-apache
service httpd reload || true

%postun -n %name-nginx
service nginx reload || true

# APACHE
%files -n %name-apache
%dir %apache_dir
%dir %apache_dir/conf.d
%config(noreplace) %attr(0640,root,root) %apache_dir/conf.d/kdav.conf

# NGINX
%files -n %name-nginx
%dir %nginx_dir
%dir %nginx_dir/sites-available
%config(noreplace) %attr(0640,nginx,nginx) %nginx_dir/sites-available/kdav.conf


%changelog
* Wed Mar 11 2020 Manfred Kutas <mkutas@kopano.com> - 0.9
- First kopano-kdav package