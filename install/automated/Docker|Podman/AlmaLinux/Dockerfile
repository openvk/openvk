#From https://gist.github.com/WerySkok/77f9c9ec134e98b0fef2b63655ba13d2 instruction
FROM almalinux/almalinux:8

#update and install httpd
RUN dnf -y update && dnf -y autoremove && dnf install -y httpd 

#Let's install EPEL and Remi repos for PHP 7.4:
RUN dnf -y install epel-release && \
dnf -y install https://rpms.remirepo.net/enterprise/remi-release-8.rpm

#Then enable modules that we need:
RUN dnf -y module enable php:remi-7.4 && \
dnf -y module enable nodejs:14

#And install dependencies:
RUN dnf -y install php php-cli php-common unzip php-zip php-yaml php-gd php-pdo_mysql nodejs git

#Don't forget about Yarn and Composer:
RUN npm i -g yarn && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php composer-setup.php --filename=composer2 --install-dir=/bin --snapshot && \
rm composer-setup.php

#We will use Percona Server for DB:
RUN dnf -y install https://repo.percona.com/yum/percona-release-latest.noarch.rpm && \
percona-release setup -y ps80 && \
dnf -y install percona-server-server percona-toolkit && \
systemctl enable mysqld && \
echo 'skip-grant-tables' >> /etc/my.cnf

#Additionally, you can install ffmpeg for processing videos.
#Then install SDL2 and ffmpeg:
RUN dnf -y localinstall --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-8.noarch.rpm && \
dnf -y install --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-8.noarch.rpm

#Then install SDL2 and ffmpeg:
RUN dnf -y localinstall --nogpgcheck https://pkgs.dyn.su/el8/base/x86_64/raven-release-1.0-2.el8.noarch.rpm && \
dnf -y --enablerepo=epel-testing,raven-extras,raven-multimedia install --nogpgcheck SDL2 && \
dnf -y install ffmpeg

#Install Chandler and OpenVk/Capcha-extention in /opt:
RUN cd /opt && \
git clone https://github.com/samukhin/chandler.git && \
cd chandler/ && \
composer2 install && \
mv chandler-example.yml chandler.yml && \
cd extensions/available/ && \
git clone https://github.com/samukhin/commitcaptcha.git && \
cd commitcaptcha/ && \
composer2 install && \
cd .. && \
git clone -b microblog https://github.com/samukhin/openvk.git && \
cd openvk/ && \
composer2 install && \
cd Web/static/js && \
yarn install && \
cd ../../../ && \
mv openvk-example.yml openvk.yml && \
ln -s /opt/chandler/extensions/available/commitcaptcha/ /opt/chandler/extensions/enabled/commitcaptcha && \
ln -s /opt/chandler/extensions/available/openvk/ /opt/chandler/extensions/enabled/openvk

#Create database
RUN cp /opt/chandler/extensions/available/openvk/install/automated/common/create_db.service /etc/systemd/system/ && \
chmod 644 /etc/systemd/system/create_db.service && \
chmod 777 /opt/chandler/extensions/available/openvk/install/automated/common/autoexec && \
systemctl enable create_db

#Make the user apache owner of the chandler folder:
RUN cd /opt && \
chown -R apache: chandler/

#Now let's create config file /etc/httpd/conf.d/10-openvk.conf and
#Also enable rewrite_module by creating /etc/httpd/conf.modules.d/02-rewrite.conf
RUN cp /opt/chandler/extensions/available/openvk/install/automated/common/10-openvk.conf /etc/httpd/conf.d/ && \
cp /opt/chandler/extensions/available/openvk/install/automated/common/02-rewrite.conf /etc/httpd/conf.modules.d/

#Make directory for OpenVK logs and make the user apache owner of it:
RUN mkdir /var/log/openvk && \
chown apache: /var/log/openvk/

#And start Apache:
#RUN systemctl enable httpd

#Start systemd
CMD ["/sbin/init"]
