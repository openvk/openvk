#From https://gist.github.com/WerySkok/77f9c9ec134e98b0fef2b63655ba13d2 instruction
FROM fedora:33

#update and install httpd
RUN dnf -y update && dnf -y autoremove && dnf install -y httpd 

#Let's install Remi repos for PHP 7.4:
RUN dnf -y install https://rpms.remirepo.net/fedora/remi-release-$(rpm -E %fedora).rpm

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

#We will use Mariadb for DB:
RUN dnf -y install mysql mysql-server && \
systemctl enable mariadb && \
echo 'skip-grant-tables' >> /etc/my.cnf

#Additionally, you can install ffmpeg for processing videos.
#You will need to use RPMFusion repo to install it:
RUN dnf -y install --nogpgcheck https://download1.rpmfusion.org/free/fedora/rpmfusion-free-release-$(rpm -E %fedora).noarch.rpm && \
dnf -y install --nogpgcheck https://download1.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-$(rpm -E %fedora).noarch.rpm

#Then install SDL2 and ffmpeg:
RUN dnf -y install --nogpgcheck SDL2 ffmpeg

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

#For login
RUN dnf -y install passwd && passwd -d root

#Start systemd
CMD ["/sbin/init"]
