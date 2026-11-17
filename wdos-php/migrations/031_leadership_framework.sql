-- v109.5: the volunteer leadership roles a woman can apply for follow the WODDI WGMN & WNNN Volunteer Leadership Framework
-- (uploaded 17 Nov 2026). WGMN uses Coordinator below country level, WNNN uses Lead; both networks use Country Representative
-- and Deputy Country Representative. Older positions stay for existing appointments but are no longer offered on the forms.
ALTER TABLE positions ADD COLUMN in_framework TINYINT NOT NULL DEFAULT 0;
UPDATE positions SET title_wgmn='Country Representative', title_wnnn='Country Representative', in_framework=1 WHERE code='country_rep';
UPDATE positions SET title_wgmn='Deputy Country Representative', title_wnnn='Deputy Country Representative', in_framework=1 WHERE code='deputy_country_rep';
UPDATE positions SET title_wgmn='State / Regional Coordinator', title_wnnn='State / Regional Lead', in_framework=1 WHERE code='state_coordinator';
UPDATE positions SET title_wgmn='Assistant State / Regional Coordinator', title_wnnn='Assistant State / Regional Lead', in_framework=1 WHERE code='assistant_state_coordinator';
UPDATE positions SET title_wgmn='LGA / District Coordinator', title_wnnn='LGA / District Lead', in_framework=1 WHERE code='district_coordinator';
UPDATE positions SET title_wgmn='Assistant LGA / District Coordinator', title_wnnn='Assistant LGA / District Lead', in_framework=1 WHERE code='assistant_district_coordinator';
UPDATE positions SET title_wgmn='Ward / Community / Cluster Coordinator', title_wnnn='Ward / Community / Cluster Lead', in_framework=1 WHERE code='cluster_coordinator';
UPDATE positions SET title_wgmn='Assistant Ward / Community / Cluster Coordinator', title_wnnn='Assistant Ward / Community / Cluster Lead', in_framework=1 WHERE code='assistant_cluster_coordinator';
UPDATE positions SET title_wgmn='Chapter Coordinator', title_wnnn='Chapter Lead', in_framework=1 WHERE code='chapter_lead';
