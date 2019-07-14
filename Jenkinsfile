pipeline {
    agent any
    environment {
        PROJECT = 'bfin--zapcore'
    }
    stages {
        stage('branch: php-5') {
            when {
                branch 'php-5'
            }
            steps {
                sh 'docker-phpunit -u 5.5 5.6'
            }
            post {
                success {
                    sh 'jenkins-postproc'
                }
            }
        }
    }
}
