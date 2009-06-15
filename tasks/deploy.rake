
namespace :deploy do

  task :sync do
    sh "rsync -azP --delete --exclude=\".git\" --exclude=\"wordpress/wp-content/uploads\" #{APP_ROOT} #{APP_SERVER}:#{DEPLOY_ROOT}"
  end

  desc "Deploy to notsquares.com"
  remote_task :deploy => :sync do
    run "cp #{DEPLOY_ROOT}/conf/production/wp-config.php #{DEPLOY_ROOT}/wordpress/wp-config.php"
  end

end
