echo "Starting Installation of Lisk-forging-failover"
cd ..
echo "Checking if Lisk-PHP has been installed already or if script runs with LiskPool"
if [ ! -d "lisk-php" ]; then
	echo "lisk-php is missing, downloading...";
	git clone https://github.com/karek314/lisk-php
	cd lisk-php
	bash setup.sh
	cd ..
fi
apt update
apt install htop screen -y
cd Lisk-forging-failover
echo "Ready to use!"