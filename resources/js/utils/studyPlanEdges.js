// Prerequisite-edge routing shared by the public study plan, the master program pages and the
// admin board. It lives apart from studyPlan.js on purpose: the public app.js imports
// studyPlan.js dynamically, and a module that admin.js shares with it ends up in a chunk that
// imports app.js, which then boots the public Alpine inside the admin panel.

const FAN_STEP = 10;

export function transitiveReduction(courses) {
    const adj = new Map();
    const edges = [];

    courses.forEach((course) => {
        if (!adj.has(course.id)) adj.set(course.id, new Set());
        (course.prerequisites || []).forEach((prereqId) => {
            if (!adj.has(prereqId)) adj.set(prereqId, new Set());
            adj.get(prereqId).add(course.id);
            edges.push({ source: prereqId, target: course.id });
        });
    });

    const reduced = [];

    edges.forEach((edge) => {
        adj.get(edge.source).delete(edge.target);

        const visited = new Set();
        const queue = [edge.source];
        let reachable = false;

        while (queue.length) {
            const current = queue.shift();
            if (current === edge.target) {
                reachable = true;
                break;
            }
            if (visited.has(current)) continue;
            visited.add(current);
            (adj.get(current) || []).forEach((next) => {
                if (!visited.has(next)) queue.push(next);
            });
        }

        if (!reachable) {
            adj.get(edge.source).add(edge.target);
            reduced.push(edge);
        }
    });

    return reduced;
}

function fanOffset(index, count, height) {
    const raw = (index * FAN_STEP) - (((count - 1) * FAN_STEP) / 2);
    const limit = Math.max(0, (height / 2) - 8);

    return Math.max(-limit, Math.min(limit, raw));
}

/**
 * SVG path for one prerequisite edge, from the right edge of the source card to the left
 * edge of the target card. Edges that leave or enter the same card are fanned out so they
 * do not share one anchor point. Returns null when the target is not to the right of the
 * source (same term or earlier term), because there is no forward link to draw.
 */
export function buildEdgePath(source, target, fan = {}) {
    const { outIndex = 0, outCount = 1, inIndex = 0, inCount = 1 } = fan;
    const startX = source.right;
    const endX = target.left;
    const gap = endX - startX;

    if (gap < 8) return null;

    const startY = source.centerY + fanOffset(outIndex, outCount, source.height || 0);
    const endY = target.centerY + fanOffset(inIndex, inCount, target.height || 0);
    const curve = Math.min(Math.max(gap * 0.22, 14), gap * 0.32);

    return `M ${startX} ${startY} C ${startX + curve} ${startY} ${endX - curve} ${endY} ${endX} ${endY}`;
}

/**
 * Routes every non-redundant prerequisite edge of a plan. `geometry` maps course id to
 * {left, right, centerY, height}; edges are ordered by the vertical position of the card at the
 * other end so fanned-out lines do not cross next to the card. Edges that do not point forward
 * are left out (see buildEdgePath).
 */
export function planEdgePaths(geometry, courses) {
    const edges = transitiveReduction(courses).filter((edge) => geometry.has(edge.source) && geometry.has(edge.target));
    const outgoing = new Map();
    const incoming = new Map();

    edges.forEach((edge) => {
        if (!outgoing.has(edge.source)) outgoing.set(edge.source, []);
        if (!incoming.has(edge.target)) incoming.set(edge.target, []);
        outgoing.get(edge.source).push(edge);
        incoming.get(edge.target).push(edge);
    });
    outgoing.forEach((list) => list.sort((a, b) => geometry.get(a.target).centerY - geometry.get(b.target).centerY));
    incoming.forEach((list) => list.sort((a, b) => geometry.get(a.source).centerY - geometry.get(b.source).centerY));

    return edges.map((edge) => {
        const outList = outgoing.get(edge.source);
        const inList = incoming.get(edge.target);
        const path = buildEdgePath(geometry.get(edge.source), geometry.get(edge.target), {
            outIndex: outList.indexOf(edge),
            outCount: outList.length,
            inIndex: inList.indexOf(edge),
            inCount: inList.length,
        });

        return path ? { sourceId: edge.source, targetId: edge.target, path } : null;
    }).filter(Boolean);
}
