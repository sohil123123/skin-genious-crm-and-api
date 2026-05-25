pipeline {
    agent any

    tools {
        nodejs "node24.9.0"
    }

    environment {
        SERVER_IP = "127.0.0.1"
        PROJECT_PATH = "/home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in"
        SSH_KEY = "/var/lib/jenkins/.ssh/id_ed25519"
    }

    stages {

        stage("Build") {
            steps {
                sh 'composer --version'
                sh 'php --version'
                sh 'node -v'
                sh 'npm -v'

                sh 'npm ci'
                sh 'composer install --no-interaction --prefer-dist --optimize-autoloader'
                sh 'npm run build'
            }
        }

        stage("Populate .env file") {
            steps {
                withCredentials([file(credentialsId: 'production_crm_env', variable: 'mySecretEnvFile')]) {
                    sh '''
                        cp -rf $mySecretEnvFile $WORKSPACE/.env
                    '''
                }
            }
        }

        stage("Verify SSH connection to server") {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        ssh -i $SSH_KEY \
                        -o StrictHostKeyChecking=no \
                        root@$SERVER_IP "
                            whoami
                        "
                    '''
                }
            }
        }

        stage("Deploy Files") {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        rsync -avzr \
                        --delete \
                        --exclude=".git" \
                        --exclude="node_modules" \
                        --exclude="storage/logs/*" \
                        --exclude=".env" \
                        -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" \
                        ./ root@$SERVER_IP:$PROJECT_PATH
                    '''
                }
            }
        }

        stage("Run Deployment Commands") {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        ssh -i $SSH_KEY \
                        -o StrictHostKeyChecking=no \
                        root@$SERVER_IP << 'EOF'

                        set -e

                        cd /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in

                        php --version

                        composer install --no-interaction --prefer-dist --optimize-autoloader

                        php artisan migrate --force --no-interaction

                        php artisan shield:generate --panel=admin --all --no-interaction || true

                        php artisan config:clear
                        php artisan cache:clear
                        php artisan route:clear
                        php artisan view:clear

                        php artisan config:cache
                        php artisan route:cache
                        php artisan view:cache

                        php artisan storage:link || true

                        chown -R www-data:www-data storage
                        chown -R www-data:www-data bootstrap/cache

                        chmod -R 775 storage
                        chmod -R 775 bootstrap/cache

                        EOF
                    '''
                }
            }
        }
    }

    post {

        success {
            echo 'Deployment completed successfully!'
        }

        failure {
            echo 'Deployment failed!'
        }

        always {
            cleanWs(
                cleanWhenNotBuilt: false,
                deleteDirs: true,
                disableDeferredWipeout: true,
                notFailBuild: true,
                patterns: [[pattern: '.gitignore', type: 'INCLUDE']]
            )
        }
    }
}