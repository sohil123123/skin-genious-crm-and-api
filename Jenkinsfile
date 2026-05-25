pipeline {
    agent any
    tools { nodejs "node24.9.0" }
    
    stages {
        stage('Build') {
            steps {
                sh 'composer --version'
                sh 'php --version'
                sh 'node -v'
                sh 'npm -v'
                
                sh 'npm ci'
                sh 'composer install -n --optimize-autoloader --no-dev'
                sh 'npm run build'
            }
        }
        
        stage('Populate .env') {
            steps {
                withCredentials([file(credentialsId: 'production_crm_env', variable: 'mySecretEnvFile')]) {
                    sh 'cp -f $mySecretEnvFile .env'
                }
            }
        }
        
        stage('Verify SSH Connection') {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh 'ssh -o StrictHostKeyChecking=no root@187.127.173.33 "whoami && echo SSH Connection Successful"'
                }
            }
        }
    }
    
    post {
        success {
            sshagent(credentials: ['jenkins']) {
                sh '''
                    # Deploy using rsync
                    rsync -vrz --delete --exclude='.git' --exclude='storage/framework/cache' \
                        -e "ssh -o StrictHostKeyChecking=no" \
                        . root@187.127.173.33:/home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in
                    
                    # Run commands on server
                    ssh -o StrictHostKeyChecking=no root@187.127.173.33 << 'EOF'
                        cd /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in
                    
                        echo "Running post-deploy tasks..."
                        php artisan config:clear
                        php artisan cache:clear
                        php artisan view:clear
                        php artisan route:clear
                        
                        php artisan migrate --force --no-interaction
                        php artisan shield:generate --panel=admin --all --no-interaction || true
                        
                        php artisan storage:link
                        php artisan config:cache
                        php artisan route:cache
                        php artisan view:cache
                        
                        chown -R www-data:www-data storage bootstrap/cache
                        chmod -R 775 storage bootstrap/cache
                        
                        echo "Deployment completed successfully!"
                    EOF
                '''
            }
        }
        
        always {
            cleanWs()
        }
    }
}