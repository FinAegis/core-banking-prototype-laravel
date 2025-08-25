#!/bin/bash

# Update TODO.md to mark completed items
sed -i 's/- \[ \] \*\*Create User Profile System\*\*/- [x] **Create User Profile System**/' TODO.md
sed -i 's/- \[ \] Design UserAggregate with event sourcing/- [x] Design UserAggregate with event sourcing/' TODO.md
sed -i 's/- \[ \] Implement profile management service/- [x] Implement profile management service/' TODO.md
sed -i 's/- \[ \] Add preference management/- [x] Add preference management/' TODO.md
sed -i 's/- \[ \] Create notification settings/- [x] Create notification settings/' TODO.md

sed -i 's/- \[ \] \*\*User Activity Tracking\*\*/- [x] **User Activity Tracking**/' TODO.md
sed -i 's/- \[ \] Create ActivityAggregate/- [x] Create ActivityAggregate/' TODO.md
sed -i 's/- \[ \] Implement activity projector/- [x] Implement activity projector/' TODO.md
sed -i 's/- \[ \] Add analytics service/- [x] Add analytics service/' TODO.md
sed -i 's/- \[ \] Create activity dashboard/- [x] Create activity dashboard/' TODO.md

sed -i 's/- \[ \] \*\*User Settings & Preferences\*\*/- [x] **User Settings \& Preferences**/' TODO.md
sed -i 's/- \[ \] Language preferences/- [x] Language preferences/' TODO.md
sed -i 's/- \[ \] Timezone settings/- [x] Timezone settings/' TODO.md
sed -i 's/- \[ \] Communication preferences/- [x] Communication preferences/' TODO.md
sed -i 's/- \[ \] Privacy settings/- [x] Privacy settings/' TODO.md

sed -i 's/- \[ \] \*\*Performance Monitoring System\*\*/- [x] **Performance Monitoring System**/' TODO.md
sed -i 's/- \[ \] Create PerformanceAggregate/- [x] Create PerformanceAggregate/' TODO.md
sed -i 's/- \[ \] Implement metrics collector/- [x] Implement metrics collector/' TODO.md
sed -i 's/- \[ \] Add performance projector/- [x] Add performance projector/' TODO.md
sed -i 's/- \[ \] Create optimization workflows/- [x] Create optimization workflows/' TODO.md

sed -i 's/- \[ \] \*\*Analytics Dashboard\*\*/- [x] **Analytics Dashboard**/' TODO.md
sed -i 's/- \[ \] Transaction performance metrics/- [x] Transaction performance metrics/' TODO.md
sed -i 's/- \[ \] System performance KPIs/- [x] System performance KPIs/' TODO.md
sed -i 's/- \[ \] User behavior analytics/- [x] User behavior analytics/' TODO.md
sed -i 's/- \[ \] Resource utilization tracking/- [x] Resource utilization tracking/' TODO.md

sed -i 's/- \[ \] \*\*Product Catalog\*\*/- [x] **Product Catalog**/' TODO.md
sed -i 's/- \[ \] Create ProductAggregate/- [x] Create ProductAggregate/' TODO.md
sed -i 's/- \[ \] Implement pricing service/- [x] Implement pricing service/' TODO.md
sed -i 's/- \[ \] Add feature management/- [x] Add feature management/' TODO.md
sed -i 's/- \[ \] Create product comparison/- [x] Create product comparison/' TODO.md

echo "TODO.md updated successfully!"
