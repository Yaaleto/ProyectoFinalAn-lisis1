import { startStimulusApp } from '@symfony/stimulus-bundle';
import NavigationController from './controllers/navigation_controller.js';

const app = startStimulusApp();
app.register('navigation', NavigationController);
