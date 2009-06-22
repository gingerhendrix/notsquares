require 'rubygems'
begin
  require 'rake'
rescue LoadError
  puts 'This script should only be accessed via the "rake" command.'
  puts 'Installation: gem install rake -y'
  exit
end
require 'rake'
require 'rake_remote_task'

$:.unshift File.dirname(__FILE__) + "/tasks/lib"

APP_ROOT = File.expand_path(File.dirname(__FILE__)) + "/"
DEPLOY_ROOT = "/var/www/notsquares.com"

APP_SERVER = "gandrew.com"
role :app_server, APP_SERVER

Dir['tasks/**/*.rake'].each { |rake| load rake }

