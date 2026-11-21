/* v109.44: #/birthday, where her birthday email and phone notification open: her card from WODDI HQ and her spoken message. */
import { birthdayCard } from '../bdaycard.js';
export async function render(root) { root.append(birthdayCard({ page: true })); }
